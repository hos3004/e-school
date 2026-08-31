#!/bin/sh
BASE=http://nginx
JAR=/tmp/c.jar
rm -f "$JAR"; touch "$JAR"
curl -s -c "$JAR" "$BASE/login" -o /dev/null
TOKEN=$(awk '$6=="XSRF-TOKEN" {print $7}' "$JAR" | sed 's/%3D/=/g')
curl -s -b "$JAR" -c "$JAR" -H "X-XSRF-TOKEN: $TOKEN" -o /dev/null \
    -X POST "$BASE/login" \
    --data-urlencode 'login=student1@demo.local' \
    --data-urlencode 'password=password'
BODY=$(curl -s -b "$JAR" -c "$JAR" "$BASE/teacher/groups")
echo "raw translation keys found: $(echo "$BODY" | grep -oE '[a-z]+\.[a-z_.]+' | grep -c 'navigation\.\|teacher\.' || true)"
echo "title: $(echo "$BODY" | grep -o '<title>[^<]*</title>' | head -1)"
