# 🍵 Thé Tip Top — API

API REST du projet Thé Tip Top (jeu-concours Master). Ce dépôt contient le code de l'application Symfony et l'ensemble des manifests Kubernetes pour le déploiement continu sur cluster K3s.

## 🌟 Stack technique

| Couche | Technologie |
|---|---|
| **Backend** | Symfony 7.3 + API Platform |
| **Base de données** | MariaDB 11 |
| **Orchestration** | Kubernetes (K3s) |
| **CI/CD** | Jenkins |
| **Ingress / TLS** | Traefik + Let's Encrypt |
| **Monitoring** | Prometheus + Grafana |

## 🏗️ Architecture

```
[Internet]
  └── Traefik (Ingress + TLS Let's Encrypt)
        ├── api.the-tip-top.jonathanlore.fr → symfony-api
        └── pma.the-tip-top.jonathanlore.fr → phpmyadmin

[Namespace: the-tip-top-api]
  ├── symfony-api        ← Application PHP/Apache
  ├── mariadb            ← Base de données (volume persistant 5Gi)
  ├── phpmyadmin         ← Interface d'administration MariaDB
  └── mysqld-exporter    ← Export des métriques MariaDB vers Prometheus

[Namespace: monitoring — repo k8s-infra]
  ├── Prometheus         ← Scrape les métriques toutes les 15s
  └── Grafana            ← Dashboards (grafana.jonathanlore.fr)
```

## 📁 Structure Kubernetes

```
k8s/
├── kustomization.yaml          ← Assemblage principal (kubectl apply -k k8s/)
├── api/                        ← Deployment + Service + Ingress Symfony
├── database/                   ← Deployment + Service + PVC MariaDB
├── phpmyadmin/                 ← Deployment + Service + Ingress PHPMyAdmin
├── mysqld-exporter/            ← Deployment + Service mysqld-exporter
└── monitoring/
    ├── kustomization.yaml
    ├── podmonitor-symfony.yaml ← Scraping /metrics/prometheus (Symfony)
    └── podmonitor-mysqld.yaml  ← Scraping :9104/metrics (mysqld-exporter)
```

## 🚀 Déploiement

Le déploiement complet est automatisé via Jenkins. En cas de besoin manuel :

### 1. Namespace

```bash
kubectl create namespace the-tip-top-api
```

### 2. Secrets

Les secrets sont normalement créés par le pipeline Jenkins. Pour les créer manuellement :

**MariaDB :**
```bash
kubectl create secret generic mariadb-secret \
  --from-literal=root-password='<ROOT_PASSWORD>' \
  --from-literal=user-password='<USER_PASSWORD>' \
  -n the-tip-top-api
```

**Symfony :**
```bash
kubectl create secret generic symfony-env \
  --from-literal=APP_ENV=prod \
  --from-literal=APP_SECRET='<APP_SECRET>' \
  --from-literal=DATABASE_URL='mysql://api_user:<USER_PASSWORD>@mariadb:3306/the_tip_top?serverVersion=11&charset=utf8mb4' \
  -n the-tip-top-api
```

**JWT :**
```bash
kubectl create secret generic jwt-keys \
  --from-file=private.pem=./config/jwt/private.pem \
  --from-file=public.pem=./config/jwt/public.pem \
  -n the-tip-top-api
```

**OAuth :**
```bash
kubectl create secret generic oauth-secrets \
  --from-literal=GOOGLE_CLIENT_ID='<ID>' \
  --from-literal=GOOGLE_CLIENT_SECRET='<SECRET>' \
  --from-literal=FACEBOOK_CLIENT_ID='<ID>' \
  --from-literal=FACEBOOK_CLIENT_SECRET='<SECRET>' \
  --from-literal=TRUSTED_PROXIES='10.0.0.0/8,172.16.0.0/12,192.168.0.0/16' \
  -n the-tip-top-api
```

**mysqld-exporter :**
```bash
kubectl create secret generic mysqld-exporter-secret \
  --from-literal=DATA_SOURCE_NAME="exporter:<PASSWORD>@tcp(mariadb:3306)/" \
  -n the-tip-top-api
```

### 3. Déploiement

```bash
kubectl apply -k k8s/
kubectl apply -k k8s/monitoring/
```

### 4. Vérification

```bash
kubectl get pods -n the-tip-top-api
kubectl get svc -n the-tip-top-api
kubectl get ingress -n the-tip-top-api
kubectl get secrets -n the-tip-top-api
```

Pods attendus :
- `symfony-api` — Running
- `mariadb` — Running
- `phpmyadmin` — Running
- `mysqld-exporter` — Running

## 🔁 Pipeline Jenkins

Le `Jenkinsfile` automatise les étapes suivantes sur chaque push sur `main` :

| Stage | Description |
|---|---|
| **Build Docker Image** | Build + push `joanisky/the-tip-top-api:<BUILD_NUMBER>` |
| **Deploy JWT Secrets** | Recrée le secret `jwt-keys` depuis les credentials Jenkins |
| **Deploy OAuth Secrets** | Crée/met à jour `oauth-secrets` |
| **Deploy Mysqld Exporter Secret** | Crée/met à jour `mysqld-exporter-secret` |
| **Deploy PodMonitors** | `kubectl apply -k k8s/monitoring/` |
| **Deploy to Kubernetes** | `kubectl apply -k k8s/` + rollout de la nouvelle image |
| **Database Migrations** | `doctrine:schema:update` + `cache:clear` |
| **Verify Deployment** | Affiche l'état des pods, services, ingress, secrets |
| **Health Check** | Attend un HTTP 200 sur `/api` avant de valider |

### Credentials Jenkins requis

| ID | Type | Usage |
|---|---|---|
| `jenkins-dockerhub` | Username/Password | Push image Docker Hub |
| `kubeconfig` | File | Accès cluster K3s |
| `jwt-private-key` | File | Clé privée JWT |
| `jwt-public-key` | File | Clé publique JWT |
| `GOOGLE_CLIENT_ID` | Secret text | OAuth Google |
| `GOOGLE_CLIENT_SECRET` | Secret text | OAuth Google |
| `FACEBOOK_CLIENT_ID` | Secret text | OAuth Facebook |
| `FACEBOOK_CLIENT_SECRET` | Secret text | OAuth Facebook |
| `MYSQLD_EXPORTER_PASSWORD` | Secret text | Connexion mysqld-exporter → MariaDB |

## 📊 Monitoring

Les métriques sont exposées via deux exporters et visualisées dans Grafana (`https://grafana.jonathanlore.fr`).

### Symfony — artprima/prometheus-metrics-bundle

Endpoint : `GET /metrics/prometheus`

| Métrique | Type | Description |
|---|---|---|
| `thetiptop_http_requests_total` | Counter | Requêtes HTTP totales |
| `thetiptop_http_2xx_responses_total` | Counter | Réponses 2xx |
| `thetiptop_http_4xx_responses_total` | Counter | Erreurs client |
| `thetiptop_request_durations_histogram_seconds` | Histogram | Temps de réponse p50/p95/p99 |

Dashboard : importer `vendor/artprima/prometheus-metrics-bundle/grafana/symfony-app-overview.json`, variable **Namespace** = `thetiptop`.

### MariaDB — mysqld-exporter

Endpoint : `:9104/metrics`

L'utilisateur MariaDB `exporter` doit avoir les droits :
```sql
GRANT PROCESS, REPLICATION CLIENT, SELECT ON *.* TO 'exporter'@'%';
```

Dashboard : importer l'ID **`14057`** depuis grafana.com.

## 🌐 Endpoints

| Service | URL |
|---|---|
| API | https://api.the-tip-top.jonathanlore.fr/api |
| PHPMyAdmin | https://pma.the-tip-top.jonathanlore.fr |

## 🛠️ Commandes utiles

```bash
# Logs en temps réel
kubectl logs -f deployment/symfony-api -n the-tip-top-api

# Shell dans le pod Symfony
kubectl exec -it deployment/symfony-api -n the-tip-top-api -- bash

# Redémarrer un déploiement
kubectl rollout restart deployment/symfony-api -n the-tip-top-api

# Describe un pod (debug)
kubectl describe pod -l app=symfony-api -n the-tip-top-api

# Vérifier les métriques mysqld-exporter
kubectl exec -n the-tip-top-api deployment/mysqld-exporter -- \
  wget -qO- http://localhost:9104/metrics | grep mysql_up
```
