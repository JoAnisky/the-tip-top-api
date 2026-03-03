# 📊 Stack Monitoring — TheTipTop

Ce dossier contient tous les manifests Kubernetes et la configuration Helm pour la stack de monitoring du projet TheTipTop.

## Architecture

```
[Symfony pod]
  └── artprima/prometheus-metrics-bundle
        └── expose GET /metrics/prometheus
              └── retourne les compteurs HTTP en format texte Prometheus

[PodMonitor]
  └── dit à Prometheus : "scrape les pods symfony-api dans the-tip-top-api"
  └── label release: kube-prometheus-stack → Prometheus Operator le détecte

[Prometheus]
  └── toutes les 15s, appelle /metrics/prometheus sur le pod Symfony
  └── stocke les valeurs dans sa base de données time-series (TSDB)

[Grafana]
  └── interroge Prometheus via des requêtes PromQL
  └── affiche les résultats en graphiques sur le dashboard
```

## Composants

| Composant | Rôle |
|---|---|
| **Prometheus** | Scrape et stocke les métriques |
| **Grafana** | Visualisation des métriques (dashboards) |
| **Alertmanager** | Gestion des alertes |
| **kube-state-metrics** | Métriques Kubernetes (pods, deployments...) |
| **node-exporter** | Métriques infra du nœud (CPU, RAM, disque) |
| **Prometheus Operator** | Gère Prometheus dynamiquement via les CRDs Kubernetes |

Tous ces composants sont installés d'un seul coup via le Helm chart **`kube-prometheus-stack`**.

## Fichiers

```
k8s/monitoring/
├── kustomization.yaml          ← liste des manifests appliqués par kubectl apply -k
├── namespace.yaml              ← namespace "monitoring"
├── helm-values.yaml            ← configuration du chart kube-prometheus-stack
├── ingress-grafana.yaml        ← exposition de Grafana via Traefik + TLS
└── podmonitor-symfony.yaml     ← cible de scraping pour le pod Symfony
```

## Concepts clés

### Prometheus Operator et PodMonitor

`kube-prometheus-stack` ne déploie pas Prometheus seul — il déploie aussi un **Prometheus Operator**, un contrôleur Kubernetes qui surveille des ressources personnalisées (CRDs) comme `PodMonitor` et `ServiceMonitor`.

Quand on crée un `PodMonitor`, l'Operator le détecte et reconfigure Prometheus automatiquement pour scraper les pods ciblés. **Sans l'Operator, il faudrait modifier manuellement la config de Prometheus à chaque nouveau service à surveiller.**

> ⚠️ L'Operator ne détecte que les PodMonitors qui ont le label `release: kube-prometheus-stack`.
> Sans ce label, le PodMonitor existe dans Kubernetes mais Prometheus l'ignore.

### Annotations `prometheus.io/scrape` vs PodMonitor

Les annotations `prometheus.io/scrape: "true"` sur les pods sont une convention de **l'ancien Prometheus standalone**. Elles ne fonctionnent pas avec `kube-prometheus-stack` qui utilise l'Operator — elles sont ignorées. Il faut obligatoirement passer par un `PodMonitor` ou un `ServiceMonitor`.

### Namespace du bundle (`thetiptop`)

Le namespace configuré dans `artprima_prometheus_metrics.yaml` est le **préfixe des noms de métriques** exposées. Avec `namespace: thetiptop`, les métriques s'appellent :

- `thetiptop_http_requests_total`
- `thetiptop_http_2xx_responses_total`
- `thetiptop_request_durations_histogram_seconds`

Ce namespace doit correspondre à la variable **Namespace** dans le dashboard Grafana.

### Stockage APCu

Le bundle stocke les compteurs en mémoire via **APCu** (`PROM_METRICS_DSN=apcu://localhost`). APCu est un cache PHP en mémoire partagée entre les workers FPM. Les métriques sont donc **perdues au redémarrage du pod** — c'est acceptable car Prometheus garde l'historique dans sa propre TSDB.

## Déploiement

Le déploiement est entièrement géré par Jenkins via le stage `Deploy Monitoring Stack` dans le `Jenkinsfile`.

```bash
# Applique namespace + ingress + podmonitor
kubectl apply -k k8s/monitoring/

# Crée le secret Grafana (valeurs depuis les credentials Jenkins)
kubectl create secret generic grafana-admin-secret \
  --from-literal=GF_SECURITY_ADMIN_USER=<user> \
  --from-literal=GF_SECURITY_ADMIN_PASSWORD=<password> \
  --namespace monitoring \
  --dry-run=client -o yaml | kubectl apply -f -

# Installe / met à jour la stack via Helm
helm upgrade --install kube-prometheus-stack \
  prometheus-community/kube-prometheus-stack \
  --namespace monitoring \
  --values k8s/monitoring/helm-values.yaml \
  --set grafana.adminPassword=<password> \
  --wait --timeout 5m
```

> Le mot de passe Grafana est passé via `--set` au moment du déploiement Jenkins et ne doit **jamais** être commité dans `helm-values.yaml`.

## Accès

| Service | URL |
|---|---|
| Grafana | https://grafana.the-tip-top.jonathanlore.fr |

Identifiants : voir les credentials Jenkins `GRAFANA_ADMIN_USER` / `GRAFANA_ADMIN_PASSWORD`.

## Dashboard Symfony

Le dashboard est importé depuis le fichier JSON fourni par le bundle :

```
vendor/artprima/prometheus-metrics-bundle/grafana/symfony-app-overview.json
```

Dans Grafana : **Dashboards → New → Import → Upload JSON file**

Après import, sélectionner la datasource Prometheus et changer la variable **Namespace** de `symfony` à `thetiptop`.

### Métriques disponibles

| Métrique | Type | Description |
|---|---|---|
| `thetiptop_http_requests_total` | Counter | Nombre total de requêtes HTTP |
| `thetiptop_http_2xx_responses_total` | Counter | Requêtes avec réponse 2xx |
| `thetiptop_http_4xx_responses_total` | Counter | Erreurs client (4xx) |
| `thetiptop_request_durations_histogram_seconds` | Histogram | Temps de réponse (p50 / p95 / p99) |

## Dashboards recommandés (à importer)

| Dashboard | ID Grafana | Description |
|---|---|---|
| Node Exporter Full | `1860` | CPU, RAM, disque, réseau du nœud |
| Kubernetes cluster | `315` | Vue globale des pods et deployments |
| MySQL Overview | `7362` | Métriques MariaDB (à venir avec mysqld-exporter) |
