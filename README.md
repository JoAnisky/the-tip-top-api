#  Thé Tip Top API
API REST développée avec Symfony 7.3 pour le projet Thé Tip Top. Ce dépôt contient à la fois le code de l'application et l'infrastructure complète pour un déploiement continu sur Kubernetes.

## 🌟 Technos du projet

**Backend** : **Symfony 7.3** - _API REST_  
**Base de données** : **MariaDB**  
**Infrastructure** : **Kubernetes** - _Orchestration des conteneurs_  
**CI/CD** : **Jenkins** - _Automatisation du pipeline de déploiement_  
**Proxy** : **Traefik** - _Contrôleur Ingress et Reverse Proxy_

## 🏗️ Architecture du projet
L'architecture est conteneurisée et déployée sur Kubernetes dans le namespace `the-tip-top-api`. Elle se compose des Pods suivants :

- `symfony-api` : Conteneur de l'application PHP/Apache.
- `mariadb` : Conteneur de la base de données, avec volume persistant.
- `phpmyadmin` : Interface d'administration web pour MariaDB.

### 📁 Structure des fichiers Kubernetes (k8s/)
Le déploiement s'appuie sur Kustomize pour gérer les manifestes Kubernetes. L'exécution de `kubectl apply -k k8s/` applique l'ensemble des ressources suivantes :

`kustomization.yaml` : Le fichier d'assemblage principal.  
`deployments/` : Fichiers `Deployment` (API, DB, PMA).  
`services/` : Fichiers `Service` de type ClusterIP.  
`ingress/` : Fichier `Ingress` pour l'exposition externe (API et PMA).  
`database/` : Fichier `pvc-db.yaml` pour la persistance des données.

## 💻 Prérequis locaux et cluster
Pour interagir avec le projet et le déployer, les outils suivants sont nécessaires :

**Docker** et **docker compose**.
**Kubernetes** (minikube, k3s ou un cluster managé).
`kubectl` pour l'administration du cluster.
Un **Ingress Controller** (`Traefik` est requis pour ce déploiement).
**Jenkins** intégré au cluster, pour l’automatisation du CI/CD.

## 🚀 Déploiement Kubernetes
Étapes à suivre pour préparer le déploiement : créer le namespace et les secrets avant d'appliquer les manifestes.

### 1. Préparation de l'environnement

####  Création du namespace

```bash
kubectl create namespace the-tip-top-api 
```

#### Création des secrets
Les secrets servent à stocker des informations sensibles. Ils sont référencés dans les fichiers `deployment-api.yaml` et `deployment-db.yaml`.

**Secret MariaDB (mariadb-secret)** : Contient les identifiants d'accès à la base de données.
```bash
kubectl create secret generic mariadb-secret \
--from-literal=root-password='mot_de_passe_root' \
--from-literal=user-password='mot_de_passe_utilisateur' \
-n the-tip-top-api
```

**Secret Symfony (symfony-env)** : Contient les variables d’environnement de l’application.
```bash
kubectl create secret generic symfony-env \
--from-literal=APP_ENV=prod \
--from-literal=APP_SECRET='clef_symfony' \
--from-literal=DATABASE_URL='mysql://api_user:mot_de_passe_utilisateur@mariadb:3306/the_tip_top?serverVersion=11&charset=utf8mb4' \
-n the-tip-top-api
```
**Note** : D'autres variables (ex: `MAILER_DSN`, `JWT_PASSPHRASE`) peuvent y être ajoutées ultérieurement.


####  Création du volume persistant

Un `PersistentVolumeClaim` (PVC) est nécessaire pour garantir la persistance des données de MariaDB (voir `k8s/database/pvc-db.yaml`). 

```yaml
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: mariadb-pvc
  namespace: the-tip-top-api
spec:
  accessModes:
    - ReadWriteOnce
  resources:
    requests:
      storage: 5Gi
  storageClassName: ""
```

**Attention VPS** : Si le cluster n'a pas de provisioner de stockage automatique, un PersistentVolume (PV) manuel pointant vers un chemin sur le disque (`/mnt/data/mariadb` par exemple) doit être créé avant d'appliquer ce PVC.

### 2. Déploiement de la stack
Chaque service Kubernetes a son propre dossier. Un fichier kustomization.yaml permet de lancer les différents fichiers de chaque dossier (pod) : deployment, ingress, service...  
Une fois les prérequis créés, lancer le déploiement complet via Kustomize :

```bash
kubectl apply -k k8s/
```

### 3. Vérification du Déploiement 

Lister les ressources créées dans le namespace (the-tip-top-api)

```bash
kubectl get pods -n the-tip-top-api
kubectl get svc -n the-tip-top-api
kubectl get ingress -n the-tip-top-api
```
Pods attendus :
- symfony-api
- mariadb
- phpmyadmin

Services attendus :
- service-api
- service-db
- service-pma

## Commandes utiles 

Logs

```bash
kubectl logs -f deployment/symfony-api -n the-tip-top-api
```

Entrer dans le conteneur (récupérer avant l'id de conteneur)

```bash
kubectl exec -it symfony-api-684bb965d9-bddkc -n the-tip-top-api -- bash
```

Voir la config du pod (par ex pour vérifier qu'un fichier de config s'est bien appliqué)

```bash
kubectl describe pod symfony-api-684bb965d9-bddkc -n the-tip-top-api
```
Redémarrer le conteneur (avec rollout on peut : `restart`, `pause`, `resume`, `undo`, `history`)

```bash
kubectl rollout restart deployment/symfony-api -n the-tip-top-api
```

Vérifier la progression de la commande rollout

```bash
kubectl rollout status deployment/symfony-api -n the-tip-top-api
```

## 🔁 Intégration CI/CD avec Jenkins

Le pipeline Jenkins automatise le flux de la CI/CD via un `Jenkinsfile` :

1. **Build Docker** : Construction de l'image Docker de l'API.
2. **Push Registry** : Envoi de l'image à la registry (`joanisky/the-tip-top-api`).
3. **Déploiement K8s** : Exécution de la commande `kubectl apply -k k8s/` pour mettre à jour les Deployment.
4. **Vérification** : Contrôle de l'état des Pods, Services et Ingress.

Pour interagir avec le cluster, Jenkins utilise un secret `kubeconfig` stocké dans ses Credentials, lui permettant d'accéder aux droits d'administration du cluster distant.

## 🌐 Accès aux Endpoints
Une fois le déploiement réussi, l'API et PHPMyAdmin sont accessibles via le reverse proxy Traefik selon les règles définies dans le manifeste Ingress :
- **API (Application Symfony)** : https://api.the-tip-top.jonathanlore.fr
- **PHPMyAdmin** : https://pma.the-tip-top.jonathanlore.fr
