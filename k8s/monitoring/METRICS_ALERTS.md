# 📊 Monitoring & Alerting — Thé Tip Top
## Résumé de soutenance

---

## Architecture

```
Symfony pod      ──→  /metrics/prometheus  ──┐
mysqld-exporter  ──→  :9104/metrics         ├──→  Prometheus  ──→  Grafana  ──→  Discord
node-exporter    ──→  métriques VPS    ──────┘
```

**Flux simplifié : Prometheus collecte → Grafana évalue → Discord notifie.**

- **Prometheus** scrape les métriques toutes les 15 secondes via des `PodMonitor` (Kubernetes Operator)
- **Grafana** évalue les règles d'alerte toutes les minutes via des requêtes PromQL
- **Discord** reçoit les notifications via un webhook configuré comme Contact Point

---

## Les 6 alertes configurées

| Règle                        | Métrique PromQL                                                                                          | Seuil   | Pending period | Catégorie  |
|------------------------------|----------------------------------------------------------------------------------------------------------|---------|----------------|------------|
| MariaDB inaccessible         | `mysql_up`                                                                                               | `< 1`   | immédiat       | BDD        |
| PVC MariaDB > 80%            | `kubelet_volume_stats_used_bytes / kubelet_volume_stats_capacity_bytes * 100`                            | `> 80%` | 5 min          | Stockage   |
| Taux d'erreurs 4xx élevé     | `sum(rate(thetiptop_http_4xx_responses_total[5m])) / sum(rate(thetiptop_http_requests_total[5m])) * 100` | `> 10%` | 2 min          | Applicatif |
| Temps de réponse p95 dégradé | `histogram_quantile(0.95, sum(rate(thetiptop_request_durations_histogram_seconds_bucket[5m])) by (le))`  | `> 2s`  | 2 min          | Applicatif |
| CPU nœud > 85%               | `100 - (avg(rate(node_cpu_seconds_total{mode="idle"}[5m])) * 100)`                                       | `> 85%` | 5 min          | Infra VPS  |
| RAM nœud > 90%               | `100 - (node_memory_MemAvailable_bytes / node_memory_MemTotal_bytes * 100)`                              | `> 90%` | 5 min          | Infra VPS  |

---

## Choix techniques

### Pourquoi Grafana Alerting plutôt qu'Alertmanager ?

Grafana est déjà déployé dans la stack — zéro composant supplémentaire à maintenir.  
Le flux est simple à démontrer en live : une seule interface pour visualiser **et** alerter.  
Alertmanager aurait ajouté de la complexité (déploiement, configuration, routing) sans valeur ajoutée pour ce projet.

### Pourquoi des pending periods différentes ?

- **Immédiat** pour MariaDB : chaque seconde compte quand la base de données est down, les utilisateurs ne peuvent plus jouer
- **5 min** pour CPU / RAM / PVC : évite les faux positifs sur des pics courts et normaux (redémarrage de pod, pic de trafic passager)
- **2 min** pour 4xx / p95 : compromis entre réactivité et stabilité — un taux d'erreur soutenu 2 minutes est un signal fiable

### Pourquoi `noDataState: OK` sur toutes les règles ?

Au démarrage du pod Grafana ou en l'absence de trafic récent, une métrique absente ne signifie pas un problème.  
Sans ce réglage, toutes les alertes firent au premier déploiement — ce qui génère du bruit et décrédibilise le système d'alerting.

### Pourquoi `histogram_quantile(0.95)` pour le temps de réponse ?

La moyenne (`avg`) est trompeuse : elle lisse les pics et masque les utilisateurs lents.  
Le **p95 à 2 secondes** signifie concrètement que 95% des utilisateurs attendent plus de 2 secondes — c'est un indicateur UX réel, pas statistique.

### Pourquoi surveiller le PVC MariaDB ?

Un PVC plein entraîne un crash immédiat de MariaDB sans avertissement.  
**Découverte concrète pendant la mise en place** : le PVC est déjà à ~69% de capacité sur 1 Gi — cette alerte a une utilité immédiate réelle, pas seulement théorique.

---

## Persistance de la configuration Grafana

Problème identifié : la configuration Grafana (dashboards, alertes, contact points) était perdue à chaque redéploiement car stockée dans SQLite à l'intérieur du container.

**Solution : ajout d'un PVC Grafana** dans `helm-values.yaml` :

```yaml
grafana:
  persistence:
    enabled: true
    size: 1Gi
```

Le volume persistant monte `/var/lib/grafana` sur le stockage du nœud, qui survit aux restarts et mises à jour du pod.

---

## Configuration des alertes (export Grafana)

Les alertes sont exportables depuis **Alerting → Alert rules → Export** au format YAML.  

Le fichier `k8s/monitoring/grafana-alert-rules.yaml` contient la configuration des alertes.

**Ce qu'il ne couvre pas ⚠️**  
Le Contact Point Discord (webhook) et la Notification Policy ne sont pas dans cet export.  
En cas de perte, il faudra recréer manuellement les valeurs suivantes dans l'UI avant d'importer les règles :

- L'URL du webhook Discord
- Le nom du contact point : Discord
- La notification policy : default contact point → Discord

```
k8s/monitoring/
├── helm-values.yaml          ← stack Prometheus/Grafana (PVC, root_url, adminUser)
├── ingress-grafana.yaml      ← exposition publique sur grafana.jonathanlore.fr
├── kustomization.yaml
└── namespace.yaml
```

```
the-tip-top-api/k8s/monitoring/
├── kustomization.yaml
├── podmonitor-symfony.yaml   ← scraping /metrics/prometheus du pod Symfony
├── podmonitor-mysqld.yaml    ← scraping :9104/metrics du pod mysqld-exporter
├── grafana-alert-rules.yaml  ← export des 6 règles d'alerte (à importer via Alerting → Import)
└── METRICS_ALERTS.md         ← documentation du système d'alerting (ce fichier)
```

---

## Tableau de bord — quel dashboard ouvrir selon la situation

| Situation                         | Dashboard Grafana                             |
|-----------------------------------|-----------------------------------------------|
| L'API répond lentement            | Symfony Application Overview → MySQL Exporter |
| Le VPS est lent ou surchargé      | Node Exporter / Nodes                         |
| Un pod crashe ou ne démarre pas   | Kubernetes / Compute Resources / Workload     |
| La BDD risque de manquer de place | Kubernetes / Persistent Volumes               |
| Erreurs réseau entre services     | Kubernetes / Networking / Namespace           |
| Les métriques semblent absentes   | Prometheus / Overview                         |
