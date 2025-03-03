
Create a Secret for the Admin Access

kubectl create secret generic admin-access --from-literal=username=admin --from-literal=password='1fd80187c225ef59aede41ac3916f462b81333db5793a468609650f9198af5f4' -n whdc

The SecretPWD has to be a sha256sum ->

echo -n "SecretPWD" | sha256sum
1fd80187c225ef59aede41ac3916f462b81333db5793a468609650f9198af5f4


