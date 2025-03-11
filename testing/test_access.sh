#!/bin/bash
#

TOKEN=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJuYW1lIjoiY3NhLWV1LW1lcnRpbi10ZXN0IiwiZW1haWwiOiJqb2VyZy5tZXJ0aW5AYnJvYWRjb20uY29tIiwiYWRtaW4iOiJmYWxzZSIsImV4cCI6IjE3NzMxNTQ4MDUifQ.pRWXX9HQ221Pj6SkrH2PyM56-Hm5fqyvgCRrm0QuRbg

# Payload example

#curl -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" -d @test.json https://whdc.shdw.fr/whdc.php
#sleep 2
curl -H "Content-Type: application/json" -H "Authorization: $TOKEN" -d @test.json https://whdc-dev.shdw.fr/whdc.php
