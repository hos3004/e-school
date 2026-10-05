#!/usr/bin/env bash
#
# تفعيل قناة واتساب (Green API) على الإنتاج.
#
# لماذا سكربت ولا شاشة في المنصة: التوكن سرّ. الأسرار لا تُدار من واجهة
# يفتحها موظفون، ولا تُكتب في محادثة. هنا يُدخَل مخفيًا، ويُختبر على المزوّد
# قبل أي تعديل، ولا يظهر في سجل الأوامر ولا في قائمة العمليات.
#
# الاستخدام:  sudo bash /opt/eschool/scripts/enable-whatsapp.sh
#
set -euo pipefail
set +x

APP_DIR=/opt/eschool
ENV_FILE="$APP_DIR/.env"
API_URL="https://7107.api.greenapi.com"
INSTANCE_ID="710722736548"
COMPOSE=(docker compose -f "$APP_DIR/docker-compose.yml" -f "$APP_DIR/docker-compose.prod.yml")

cd "$APP_DIR"

if [ ! -w "$ENV_FILE" ] && [ "$(id -u)" != "0" ]; then
  echo "شغّل الأمر بـ sudo." >&2
  exit 1
fi

echo "تفعيل واتساب — المثيل $INSTANCE_ID على $API_URL"
echo

# الإدخال مخفي: لا يظهر على الشاشة ولا يُحفظ في تاريخ الأوامر.
read -rsp "ألصق apiTokenInstance من لوحة Green API ثم اضغط Enter: " GREEN_TOKEN
echo
echo

if [ -z "${GREEN_TOKEN:-}" ]; then
  echo "لم يُدخَل توكن. لم يتغير شيء." >&2
  exit 1
fi

if ! printf '%s' "$GREEN_TOKEN" | grep -qE '^[A-Za-z0-9]+$'; then
  echo "صيغة التوكن غير متوقعة (يُنتظر حروف وأرقام فقط). لم يتغير شيء." >&2
  exit 1
fi

# ── 1) التحقق من المزوّد قبل لمس أي إعداد ───────────────────────────────
# التوكن يمر عبر --config من stdin لا عبر سطر الأوامر، فلا يظهر في ps.
echo "› فحص التوكن عند Green API…"
BODY_FILE="$(mktemp)"; chmod 600 "$BODY_FILE"
HTTP_CODE="$(
  {
    echo "url = \"$API_URL/waInstance$INSTANCE_ID/getStateInstance/$GREEN_TOKEN\""
    echo 'silent'
    echo 'show-error'
    echo 'max-time = 20'
  } | curl --config - -o "$BODY_FILE" -w '%{http_code}' || echo "000"
)"
STATE_JSON="$(cat "$BODY_FILE" 2>/dev/null || true)"
rm -f "$BODY_FILE"

case "$HTTP_CODE" in
  200) : ;;
  401|403)
    echo "✗ المزوّد رفض التوكن (HTTP $HTTP_CODE)." >&2
    echo "  انسخ apiTokenInstance من لوحة Green API مجددًا: اضغط أيقونة العين لإظهاره" >&2
    echo "  ثم أيقونة النسخ، واحذر مسافة زائدة في أوله أو آخره. لم يتغير شيء." >&2
    exit 1 ;;
  000)
    echo "✗ تعذّر الوصول إلى $API_URL من هذا الخادم (مشكلة شبكة لا توكن). لم يتغير شيء." >&2
    exit 1 ;;
  *)
    echo "✗ رد غير متوقع من المزوّد (HTTP $HTTP_CODE): ${STATE_JSON:-(بلا محتوى)}. لم يتغير شيء." >&2
    exit 1 ;;
esac

if ! printf '%s' "$STATE_JSON" | grep -q '"authorized"'; then
  echo "✗ المثيل ليس authorized. رد المزوّد: ${STATE_JSON:-(بلا محتوى)}" >&2
  echo "  افتح لوحة Green API وأعد ربط الهاتف بمسح رمز QR، ثم أعد تشغيل الأمر. لم يتغير شيء." >&2
  exit 1
fi

echo "  ✓ التوكن صالح والمثيل authorized."

# ── 2) نسخة احتياطية من ملف الإعداد ─────────────────────────────────────
BACKUP="$ENV_FILE.bak-$(date -u +%Y%m%dT%H%M%SZ)"
cp -p "$ENV_FILE" "$BACKUP"
echo "› نسخة من الإعداد: $BACKUP"

# ── 3) كتابة المفاتيح ───────────────────────────────────────────────────
TMP="$(mktemp)"
chmod 600 "$TMP"
# نحذف أي مفاتيح قديمة لنفس الغرض حتى لا يتكرر المفتاح ويغلب الأول.
grep -vE '^(WHATSAPP_ENABLED|WHATSAPP_PROVIDER|GREEN_API_URL|GREEN_API_INSTANCE_ID|GREEN_API_TOKEN)=' \
  "$ENV_FILE" > "$TMP"
{
  echo ""
  echo "# قناة واتساب — Green API"
  echo "WHATSAPP_ENABLED=true"
  echo "WHATSAPP_PROVIDER=green_api"
  echo "GREEN_API_URL=$API_URL"
  echo "GREEN_API_INSTANCE_ID=$INSTANCE_ID"
  echo "GREEN_API_TOKEN=$GREEN_TOKEN"
} >> "$TMP"

OWNER="$(stat -c '%U:%G' "$ENV_FILE")"
cat "$TMP" > "$ENV_FILE"
rm -f "$TMP"
chown "$OWNER" "$ENV_FILE"
chmod 600 "$ENV_FILE"
unset GREEN_TOKEN
echo "› كُتبت المفاتيح في .env (الصلاحيات 600)."

# ── 4) إعادة تحميل التطبيق ──────────────────────────────────────────────
echo "› مسح ذاكرة الإعداد وإعادة تشغيل الخدمات…"
"${COMPOSE[@]}" exec -T app php artisan config:clear >/dev/null
"${COMPOSE[@]}" restart app horizon scheduler >/dev/null
sleep 6

# ── 5) التحقق ───────────────────────────────────────────────────────────
echo
echo "النتيجة:"
"${COMPOSE[@]}" exec -T app php artisan config:show notifications 2>/dev/null \
  | grep -E 'whatsapp ⇁ (enabled|provider)|green_api ⇁ (api_url|instance_id)' \
  || true

ENABLED="$("${COMPOSE[@]}" exec -T app php artisan config:show notifications 2>/dev/null \
  | grep -cE 'whatsapp ⇁ enabled .* true' || true)"

echo
if [ "${ENABLED:-0}" -ge 1 ]; then
  echo "✓ قناة واتساب مفعّلة الآن."
  echo "  افتح ملف أي طالب ← أيقونة المراسلة: سيظهر «واتساب» ضمن القنوات."
  echo "  ولتوجيه الإشعارات التلقائية: /manage/settings ← قسم الإشعارات."
else
  echo "✗ القناة ما زالت مطفأة. استرجع الإعداد بـ:  sudo cp $BACKUP $ENV_FILE"
  exit 1
fi
