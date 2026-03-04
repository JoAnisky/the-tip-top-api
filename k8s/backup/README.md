# 🗄️ Backup automatisé — Restic + Google Drive

Documentation du système de sauvegarde automatique de la base de données MariaDB.

---

## Vue d'ensemble

```
CronJob K8s (2h/nuit)
    └── mysqldump → dump.sql (34 MiB)
        └── restic backup → chiffrement + déduplication
            └── rclone → Google Drive (restic-thetiptop/)
```

| Composant | Rôle |
|---|---|
| **Restic** | Chiffrement, déduplication, gestion des snapshots et rétention |
| **rclone** | Pont entre Restic et Google Drive (Restic ne supporte pas GDrive nativement) |
| **CronJob K8s** | Déclenchement automatique tous les jours à 2h du matin |

### Ce qui est sauvegardé

- ✅ Dump SQL complet de MariaDB (`--all-databases`)
- Les Secrets K8s et la config Helm sont versionnés dans Git — pas besoin de les sauvegarder séparément

---

## Fichiers

```
k8s/backup/
├── cronjob-backup.yaml   # Le CronJob Kubernetes
├── kustomization.yaml    # Référencé par kubectl apply -k k8s/backup/
└── README.md             # Ce fichier
```

---

## Secrets Kubernetes requis

Deux secrets doivent exister dans le namespace `the-tip-top-api` avant le déploiement.
Ils sont créés/mis à jour automatiquement par le pipeline Jenkins.

| Secret | Clé | Contenu |
|---|---|---|
| `restic-secret` | `RESTIC_PASSWORD` | Mot de passe de chiffrement Restic |
| `rclone-config` | `rclone.conf` | Config OAuth rclone (token Google Drive) |

> ⚠️ **Le mot de passe Restic est la clé de tout.** Sans lui, aucune restauration n'est possible.
> Il est stocké dans Jenkins Credentials (ID : `RESTIC_PASSWORD`).

---

## Politique de rétention

`--keep-daily 7`  

Garde le snapshot le plus récent de chacun des 7 derniers jours.

```text
25 mars, 26 mars, 27 mars, 28 mars, 29 mars, 30 mars, 31 mars  ✅ gardés
```

`--keep-weekly 4`  

Garde le snapshot le plus récent de chacune des 4 dernières semaines.

```text
Semaine du 24 mars  → 1 snapshot gardé  ✅ (probablement déjà couvert par daily)
Semaine du 17 mars  → 1 snapshot gardé  ✅
Semaine du 10 mars  → 1 snapshot gardé  ✅
Semaine du  3 mars  → 1 snapshot gardé  ✅

```

`--keep-monthly 2`  

Garde le snapshot le plus récent de chacun des 2 derniers mois.

```text
Mars   → 1 snapshot gardé  ✅ (probablement déjà couvert par weekly)
Février → 1 snapshot gardé ✅
```

__Ce qui est supprimé__

Tout le reste — c'est-à-dire les snapshots de février qui ne sont pas "le plus récent du mois", et tout ce qui est antérieur à 2 mois.
En pratique sur 90 snapshots, Restic en garde environ 10-12 et supprime les ~78 autres.
Le `--prune` supprime les données orphelines après chaque nettoyage.

__Le point clé à retenir__  

Les règles se cumulent — un snapshot peut être gardé par plusieurs règles à la fois. Restic garde tout snapshot qui satisfait au moins une des règles. Ce n'est pas "7 + 4 + 2 = 13 snapshots distincts" car il y a des chevauchements, mais c'est l'ordre de grandeur.

---

## Opérations courantes

> Toutes les commandes suivantes se lancent **depuis le VPS** en root,
> avec `rclone.conf` présent dans `/root/.config/rclone/`.
> Copier ce fichier dans le repertoire `/autreuser/.config/rclone/` d'un autre utilisateur pour l'utiliser en non root

### Lister les snapshots disponibles

```bash
restic -r rclone:gdrive:restic-thetiptop snapshots
```

Exemple de sortie :
```
ID        Time                 Host        Tags             Paths     Size
--------------------------------------------------------------------------
4854fbe1  2026-03-04 09:22:40  ...         cronjob,mariadb  /backup   34.446 MiB
a1b2c3d4  2026-03-05 02:00:00  ...         cronjob,mariadb  /backup   34.447 MiB
```

### Déclencher un backup manuel immédiat

```bash
kubectl create job --from=cronjob/restic-backup restic-backup-manual -n the-tip-top-api
```

Suivre les logs :
```bash
kubectl logs -f job/restic-backup-manual -n the-tip-top-api
```

Nettoyer le job après :
```bash
kubectl delete job restic-backup-manual -n the-tip-top-api
```

### Vérifier l'intégrité du repo

```bash
restic -r rclone:gdrive:restic-thetiptop check
```

### Voir le contenu d'un snapshot

```bash
# Snapshot le plus récent
restic -r rclone:gdrive:restic-thetiptop ls latest

# Snapshot spécifique (utiliser l'ID obtenu avec snapshots)
restic -r rclone:gdrive:restic-thetiptop ls 4854fbe1
```

---

## Procédure de restauration

### Étape 1 — Récupérer le dump SQL

```bash
# Restaurer le dernier snapshot dans /tmp/restore-test
restic -r rclone:gdrive:restic-thetiptop restore latest --target /tmp/restore-test

# Ou un snapshot spécifique par ID
restic -r rclone:gdrive:restic-thetiptop restore 4854fbe1 --target /tmp/restore-test
```

Le dump se trouve ensuite dans :
```
/tmp/restore-test/backup/dump.sql
```

### Étape 2 — Copier le dump dans le pod MariaDB

```bash
kubectl cp /tmp/restore-test/backup/dump.sql \
  the-tip-top-api/$(kubectl get pod -n the-tip-top-api -l app=mariadb -o jsonpath='{.items[0].metadata.name}'):/tmp/dump.sql
```

### Étape 3 — Importer dans MariaDB

```bash
kubectl exec -n the-tip-top-api \
  $(kubectl get pod -n the-tip-top-api -l app=mariadb -o jsonpath='{.items[0].metadata.name}') \
  -- bash -c 'mariadb -u root -p"$MARIADB_ROOT_PASSWORD" < /tmp/dump.sql'
```

### Étape 4 — Vérifier

```bash
kubectl exec -n the-tip-top-api \
  $(kubectl get pod -n the-tip-top-api -l app=mariadb -o jsonpath='{.items[0].metadata.name}') \
  -- mariadb -u root -p"$MARIADB_ROOT_PASSWORD" -e "SHOW DATABASES;"
```

---

## Diagnostic — En cas de problème

### Le CronJob ne s'est pas déclenché

```bash
# Lister les jobs programmés
kubectl get cronjob -n the-tip-top-api

# Exemple de sortie
NAME            SCHEDULE    TIMEZONE   SUSPEND   ACTIVE   LAST SCHEDULE   AGE
restic-backup   0 2 * * *   <none>     False     0        <none>          4h22m
```

```bash
# Vérifier l'historique des jobs
kubectl get jobs -n the-tip-top-api

# Vérifier les événements du CronJob
kubectl describe cronjob restic-backup -n the-tip-top-api
```

### Un job a échoué


```bash
# Lister les pods du job échoué
kubectl get pods -n the-tip-top-api | grep restic

# Lire les logs
kubectl logs <nom-du-pod> -n the-tip-top-api
```

### Erreur d'authentification Google Drive

Le token OAuth rclone expire rarement mais peut être révoqué.
Si il y'a `OAUTH2 token expired` ou `403 Forbidden` :

1. Régénérer la config rclone __en local__ : `rclone config reconnect gdrive:`
2. Mettre à jour le credential Jenkins `rclone-config` avec le nouveau `~/.config/rclone/rclone.conf`
3. Relancer le pipeline Jenkins pour mettre à jour le Secret K8s

### Vérifier que les Secrets K8s sont bien présents

```bash
kubectl get secrets -n the-tip-top-api | grep -E "restic|rclone"
```

---

## Google Drive — Structure du repo

Le dossier `restic-thetiptop/` dans Google Drive contient des fichiers **chiffrés et non lisibles directement**.
C'est normal — tout passe obligatoirement par Restic avec le mot de passe.

```
restic-thetiptop/
├── config          → Métadonnées du repo
├── keys/           → Clé de chiffrement (protégée par le mot de passe)
├── snapshots/      → Un fichier par snapshot (métadonnées : date, host, tags)
├── index/          → Index des blocs pour accélérer les opérations
└── packs/          → Données réelles, dédupliquées et chiffrées
```
