/**
 * ULID للمتصفح — يُستخدم مفتاحًا لعدم تكرار الإرسال.
 *
 * كل ضغطة إرسال تحمل معرّفًا جديدًا؛ إعادة إرسال النموذج نفسه (نقرة مزدوجة،
 * أو إعادة محاولة الشبكة) تصل بالمعرّف ذاته فيتعرّف عليها الخادم ولا يكرر
 * الرسائل. التوليد محلي لأن معرّفًا يأتي مع الصفحة يعني معرّفًا واحدًا لكل
 * زيارة، فتسقط الرسالة الثانية المقصودة.
 */
const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

function randomChars(length: number): string {
    const bytes = new Uint8Array(length);
    crypto.getRandomValues(bytes);

    let output = '';
    for (let index = 0; index < length; index += 1) {
        output += ENCODING.charAt((bytes[index] ?? 0) % ENCODING.length);
    }

    return output;
}

function encodeTime(time: number, length: number): string {
    let output = '';
    let remaining = time;

    for (let index = length - 1; index >= 0; index -= 1) {
        output = ENCODING.charAt(remaining % ENCODING.length) + output;
        remaining = Math.floor(remaining / ENCODING.length);
    }

    return output;
}

export function ulid(): string {
    return encodeTime(Date.now(), 10) + randomChars(16);
}
