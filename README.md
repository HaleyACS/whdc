# whdc 1.0-16 by J. Mertin -- smurphy@solsys.org
Purpose: Web Hook data collector

# Description
The whdc.php script collects data from a json submission, and dumps it
into a sqlite database. whdclist.php will display the data bsed on the requestor
identified by the authentication token.

The deployment happens through 2 container - application (based on php-fpm)
and nginx frontend. Once deployed, a script enables the user to add new tokens.   

The code is licensed under the GPL v2 (see [LICENCE.md](LICENSE.md)) file joined to this repo.   
The reason is that it is based on weather (weather data json collector) that is GPL'd.


The following points need to be taken into account.

- The deployment happens as user www-data/apache -> no priviliged access.
- The authentication access is done using tokens. Token vlaidity is set to 1 year from now.
- The system uses a small sqlite3 database. It will be created at container start if non existent.
- The database will delete every entry after 25 - means only 25 entries will be held in the database

### Dependencies / Mandatory

- FQDN resolvable by the WebHook sender
- The Kubernetes ingress needs to have a certificate. It would be good
  to have a certificate issued for the FQDN or access will fail du to invalid certificate.

### security

There is none ... This prototype is to be deployed for the testing phase, and removed after!

- The access control is handled through tokens match only.
- The tokens.inc file is found in the files directory. It will take a binary array entry to define a valid entry: Name => Token
- The token validity is not checked. It's sole purpose is to differentiate the submitters!


### Current status

- You create tokens under: https://whdc.shdw.fr/whdctokens.php
- You can see the submitted events here: https://whdc.shdw.fr/whdclist.php
- You submit to: https://whdc.shdw.fr/whdc.php - a example test_access.sh script is provided to test the setup.

### Usage

1. Create a token in the https://whdc.shdw.fr/whdctokens.php UI.
2. Configure the Webhook Notification channel using that token in the DX O2 settings menue.
3. Check the submitted token (note you need the token) under https://whdc.shdw.fr/whdclist.php
Youu have to reload the whdclist manually. Eventually a auto-reload can be addded, but it is not required.


### Technical

For the system to work correctly, 2 variables are mandatory in in the json submission:

- "Alarm Status": Usually it has the subject in it.
- "Alarm Name": Current status of the event.

These will be used to build the title on whdclist.php.

Example json submission in the DX OI Webhook Notification UI settings:   
```
{
 "Action Status": "${action_status}",
 "Alarm Name": "${alarm_name}",
 "dedup_key": "${alert_external_id}#${external_id}",
 "event_action": "${status}",
 "payload": "[object Object]",
 "routing_key": "",
 "Action Status": "${action_status}",
 "Acknowledged": "${acknowledged}",
 "APM Alarm Unique Id": "${apm_alarm_unique_id}",
 "Agent": "${agent}",
 "Alarm Age": "${alarmAge}",
 "Alarm Description": "${alarm_description}"
}
```

You can send a curl-based test with:
```
curl -H "Content-Type: application/json" -H "Authorization: $TOKEN" -d @test.json https://whdc.shdw.fr/whdc.php
```
Make sure you have set the TOKEN variable first and that the json text is inside the test.json file.

### Build the images

In the git directory, just issue the build.sh script. It will ask the required details.
For submiting to the image to the repo, update the REPO variable in the build.sh script.

### Kubernets deployment


The 21-whdc-deployment.yaml file needs to be adapted to reflect the korrect Image version pushed
to the repository by the build script.

Check the [README.md](k8s/README.md) in the k8s directory for directions.


