#!/bin/sh
BASE=http://nginx
JAR=/tmp/c.jar
BODY=$(curl -s -b "$JAR" -c "$JAR" "$BASE/teacher/groups")
echo "$BODY" > /tmp/tg.html
# مفاتيح ترجمة خام معروضة كنص مرئي: >xxx.yyy.zzz<
echo "visible raw keys: $(grep -oE '>[a-z]+\.[a-z]+(\.[a-z]+)*<' /tmp/tg.html | sort -u | head -10)"
echo "---"
echo "arabic content sample: $(echo "$BODY" | grep -oE '[\p{Arabic}]{4,}' 2>/dev/null | head -3)"
echo "bytes: $(wc -c < /tmp/tg.html)"
