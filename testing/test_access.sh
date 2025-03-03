#!/bin/bash
#

TOKEN=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJuYW1lIjoiY3NhLWV1LW1lcnRpbiIsImVtYWlsIjoiam9lcmcubWVydGluQGJyb2FkY29tLmNvbSIsImFkbWluIjoiZmFsc2UiLCJleHAiOiIxNzY5MDE1MDMzIn0.8kk4W8gkeWma10eM33TK6xlaJKVRh1MFN0mQQTp_zBo

# Payload example

#curl -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" -d @test.json https://whdc.shdw.fr/whdc.php
#sleep 2
curl -H "Content-Type: application/json" -H "Authorization: $TOKEN" -d @test.json https://whdc.shdw.fr/whdc.php
