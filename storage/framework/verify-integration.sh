#!/bin/sh
# تحقق حي من مسارات التكامل الجديدة لكل دور.
BASE=http://nginx
JAR=/tmp/int.jar

xsrf() { awk '$6=="XSRF-TOKEN" {print $7}' "$JAR" | sed 's/%3D/=/g'; }

login() {
    rm -f "$JAR"; touch "$JAR"
    curl -s -c "$JAR" "$BASE/login" -o /dev/null
    curl -s -b "$JAR" -c "$JAR" -o /dev/null \
        -H "X-XSRF-TOKEN: $(xsrf)" \
        -X POST "$BASE/login" \
        --data-urlencode "login=$1" \
        --data-urlencode "password=password"
}

check() { echo "$1 $2 => $(curl -s --max-time 60 -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" "$BASE$2")"; }

login student1@demo.local
check "student" /student/profile
check "student" /student/programs
check "student" /student/group
check "student" /student/notifications

login demo.teacher1@demo.local
check "teacher" /teacher/profile
check "teacher" /teacher/groups
check "teacher" /teacher/students
check "teacher" /teacher/availability
check "teacher" /teacher/notifications

# ولي أمر: نستخدم حساب guardian ديمو موجود
login guardian.dgcesd@demo.local
check "guardian" /guardian/notifications

# رفض الدور الخاطئ: طالب على صفحة معلم
login student1@demo.local
check "student-x" /teacher/groups
