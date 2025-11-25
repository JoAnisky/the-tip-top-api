# 🔒 Sécurisation de l'Accès à phpMyAdmin via Traefik (BasicAuth)
L'authentification HTTP basique (BasicAuth) est une méthode rapide et simple pour protéger un service non sécurisé (comme phpMyAdmin) en utilisant une Middleware CRD de Traefik. Elle nécessite un identifiant et un mot de passe avant que la requête n'atteigne le service backend.

## 1. 🔑 Génération du Fichier `htpasswd`
Générer la chaîne chiffrée de l'utilisateur et du mot de passe en utilisant l'outil htpasswd.

Deux méthodes de génération sont possibles :

**Avec l'outil local htpasswd**  

Si le paquet `apache2-utils` est installé sur le système :
```bash
htpasswd -nbB monuser monmotdepasse
```

**Via Docker (ne nécessite pas l'installation locale de l'outil)**
```Bash
docker run --rm httpd:2.4-alpine htpasswd -nbB monuser monmotdepasse
```

La sortie générée doit être conservée, elle ressemble à ceci :

`monuser:$apr1$abcd1234$....`

## 2. 🛡️ Création du Secret Kubernetes
Il est nécessaire de créer un Secret Kubernetes pour stocker la ligne `htpasswd` générée. Ce Secret sera référencé par la Middleware Traefik.

```bash
kubectl create secret generic pma-basic-auth \
--from-literal=users='monuser:$apr1$abcd1234$....' \
-n the-tip-top-api
```

## 3. ⚙️ Création de la Middleware Traefik
Un Middleware de type basicAuth est ensuite définie. Elle indique à Traefik d'utiliser le Secret créé ci-dessus. Cette ressource doit être déployée dans le même Namespace que l'Ingress ciblé.


```yaml
apiVersion: traefik.containo.us/v1alpha1
kind: Middleware
metadata:
    name: pma-auth
    namespace: the-tip-top-api
spec:
  basicAuth:
  secret: pma-basic-auth
```

**Application de la Middleware :**

```bash
kubectl apply -f middleware-pma-auth.yaml
```

## 4. 🌐 Référence dans l'Ingress
L'étape finale consiste à modifier la ressource `Ingress` qui expose phpMyAdmin en ajoutant l'annotation de Middleware.

Il faut s'assurer que l'annotation `traefik.ingress.kubernetes.io/router.middlewares` référence correctement la nouvelle Middleware (`pma-auth`) et le type de ressource Kubernetes (`@kubernetescrd`).

```yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: phpmyadmin-ingress
  namespace: the-tip-top-api
  annotations:
    traefik.ingress.kubernetes.io/router.entrypoints: web,websecure
    traefik.ingress.kubernetes.io/router.tls: "true"
    traefik.ingress.kubernetes.io/router.tls.certresolver: letsencrypt
    traefik.ingress.kubernetes.io/router.middlewares: pma-auth@kubernetescrd # <- Référence du Middleware
spec:
  tls:
    - hosts:
        - pma.the-tip-top.jonathanlore.fr
      secretName: pma-the-tip-top-tls
  rules:
    - host: pma.the-tip-top.jonathanlore.fr
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: phpmyadmin
                port:
                  number: 80
```
Une fois la ressource `Ingress` appliquée, Traefik demandera un login/mot de passe via une pop-up de navigateur avant d'autoriser l'accès à phpMyAdmin.
