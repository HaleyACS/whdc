### Instructions - Mariadb deployment for whdc 

### Create a database access secret
```
kubectl create secret generic whdc-db-access --from-literal=username=whdc --from-literal=password='SecretPWD' --from-literal=rootpassword='RootSecretPWD' -n whdc
```

This one is required for accessing the databas. The rootpassword is
only required for the database structure to be generated at first
deployment. With these information available, and the environment
variables MYSQL_ROOT_PASSWORD, MYSQL_USER and MYSQL_PASSWORD provided
with valid entries through the deployment yaml file, the DB installer will create the database and user+pwd.   

The whdc installer will then create the database structure once the DB s available.

Once the database has been deployed, you'll be able to see if the
/var/www/files/.INSTALLED file is present. it will contain the data of
the create of the DB structure.


For the whdc tgo run, only the MYSQL_USER and MYSQL_PASSWORD will be
required to access the DB.
