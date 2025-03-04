### Create a Secret for the Admin Access [mandatory]
```
~# kubectl create secret generic admin-access --from-literal=username=admin --from-literal=password='1fd80187c225ef59aede41ac3916f462b81333db5793a468609650f9198af5f4' -n whdc
```

The SecretPWD has to be a sha256sum, create it with:   
```
~# echo -n "SecretPWD" | sha256sum
1fd80187c225ef59aede41ac3916f462b81333db5793a468609650f9198af5f4
```

### Create a secret for the registry access [optional]

If using your own docker registry to pull images, and if that registry
needs a login/password access, create one:   
```
kubectl --namespace <NAMESPACE> create secret docker-registry whdc-serviceaccount --docker-username <USERNAME> --docker-password <PASSWORD> --docker-server <DOCKER.REGISTRY.TPL>:<PORT> -o yaml
```

Create a file to load it with:
```
---
apiVersion: v1
kind: ServiceAccount
metadata:
  name: whdc-serviceaccount
  namespace: <NAMESPACE>
imagePullSecrets:
- name: whdc-serviceaccount
```

You will have to enter "whdc-serviceaccount" into the deployment file from which you want to download the image from:
```
serviceAccountName: whdc-serviceaccount
```
