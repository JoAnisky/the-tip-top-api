# 📊 Monitoring — TheTipTop API

Ce dossier contient les ressources de découverte Prometheus spécifiques au projet TheTipTop.

> ℹ️ La stack Prometheus/Grafana elle-même (namespace, ingress, Helm) est gérée dans le
> repo d'infrastructure : `k8s/monitoring/`

## Contenu

```
k8s/monitoring/
├── kustomization.yaml          ← appliqué par le pipeline Jenkins de l'API
├── podmonitor-symfony.yaml     ← scraping du pod Symfony (/metrics/prometheus)
└── podmonitor-mysqld.yaml      ← scraping du pod mysqld-exporter (:9104/metrics)
```

## Architecture

```
[Symfony pod]
  └── artprima/prometheus-metrics-bundle
        └── expose GET /metrics/prometheus

[mysqld-exporter pod]
  └── prom/mysqld-exporter
        └── expose GET :9104/metrics
              └── se connecte à MariaDB via DATA_SOURCE_NAME

[PodMonitor symfony + PodMonitor mysqld]
  └── label release: kube-prometheus-stack → Prometheus Operator les détecte
  └── Prometheus scrape les deux endpoints toutes les 15s

[Grafana]
  └── interroge Prometheus via PromQL
  └── affiche les dashboards Symfony et MariaDB
```

## Concepts clés

### Pourquoi les PodMonitors sont dans ce repo et pas dans k8s ?

Les `PodMonitor` sont couplés à l'application — ils définissent **comment scraper les pods
de ce projet**. Si le projet disparaît, ses PodMonitors doivent disparaître avec lui.
La stack Prometheus/Grafana est transverse et vit dans le repository dédié à l'infrastructure : `k8s`, mais la configuration
de découverte de chaque projet reste dans le repo du projet.

### Label obligatoire

```yaml
labels:
  release: kube-prometheus-stack   # ← sans ce label, Prometheus ignore le PodMonitor
```

### Prometheus Operator

`kube-prometheus-stack` déploie un **Prometheus Operator** qui surveille les ressources
`PodMonitor` et `ServiceMonitor` dans tout le cluster. Quand un PodMonitor est créé ou
modifié, l'Operator reconfigure Prometheus automatiquement — pas besoin de redémarrer quoi que ce soit.

## Déploiement

Géré automatiquement par le stage `Deploy PodMonitor` du Jenkinsfile de ce repo :

```bash
kubectl apply -k k8s/monitoring/
```

## Métriques disponibles

### Symfony (`artprima/prometheus-metrics-bundle`)

| Métrique | Type | Description |
|---|---|---|
| `thetiptop_http_requests_total` | Counter | Nombre total de requêtes HTTP |
| `thetiptop_http_2xx_responses_total` | Counter | Requêtes avec réponse 2xx |
| `thetiptop_http_4xx_responses_total` | Counter | Erreurs client (4xx) |
| `thetiptop_request_durations_histogram_seconds` | Histogram | Temps de réponse (p50/p95/p99) |

> Le préfixe `thetiptop` correspond au `namespace` configuré dans
> `config/packages/artprima_prometheus_metrics.yaml`.
> Il doit correspondre à la variable **Namespace** dans le dashboard Grafana.

### MariaDB (`mysqld-exporter`)

| Métrique | Description |
|---|---|
| `mysql_up` | État de la connexion (1 = OK) |
| `mysql_global_status_connections` | Connexions totales |
| `mysql_global_status_queries` | Requêtes totales |
| `mysql_global_status_slow_queries` | Requêtes lentes |
| `mysql_global_status_innodb_buffer_pool_read_requests` | Performances InnoDB |

## Dashboards Grafana

| Dashboard | Source | Description |
|---|---|---|
| Symfony App Overview | `vendor/artprima/prometheus-metrics-bundle/grafana/symfony-app-overview.json` | Métriques HTTP Symfony |
| MySQL Overview | Import ID `7362` sur grafana.com | Métriques MariaDB |

### Importer le dashboard Symfony

1. **Dashboards → New → Import → Upload JSON file**
2. Fichier : `vendor/artprima/prometheus-metrics-bundle/grafana/symfony-app-overview.json`
3. Changer la variable **Namespace** de `symfony` à `thetiptop`

### Importer le dashboard MariaDB

1. **Dashboards → New → Import**
2. ID : `7362`
3. Sélectionner la datasource Prometheus
