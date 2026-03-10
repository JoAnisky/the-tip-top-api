# Jenkins CI/CD — Thé Tip Top API

## Pipeline

```
Push GitHub → Checkout → Tests → Publish Reports → Build & Push Docker
                                                           │
                                              (main seulement)
                                                           │
                                          Deploy Secrets → Deploy K8s
                                          → DB Migrations → Health Check
                                          → Notification Discord
```

---

## Principe : `agent none`

Aucun executor Jenkins n'est réservé pour l'ensemble du pipeline. Chaque stage déclare son propre agent — les stages qui ont besoin de PHP ou de K8s tournent dans des **pods éphémères** provisionnés à la demande.

---

## Stages

**Checkout** -- clone le repo, détecte la branche pour les conditions de déploiement.

**Tests** -- pod K8s éphémère avec deux containers qui partagent le même réseau localhost :

| Container | Image | Rôle |
|---|---|---|
| `php` | `joanisky/the-tip-top-api:latest` | Exécute PHPUnit |
| `mariadb` | `mariadb:10.11` | Base de test isolée, détruite à la fin du build |

Déroulement du script :
1. Fix ownership Git (workspace Jenkins monté dans un container root)
2. Attente MariaDB prête (boucle PDO PHP, max 20 tentatives)
3. Génération clés JWT temporaires via `openssl` — les vraies sont dans un secret K8s inaccessible ici
4. `composer install` (avec require-dev pour PHPUnit)
5. Création `.env.test.local` avec la `DATABASE_URL` du pod
6. `doctrine:schema:create --env=test`
7. `APP_ENV=test ./vendor/bin/phpunit --log-junit`

**Publish Reports** -- unstash + publication JUnit dans Jenkins. `skipDefaultCheckout true` obligatoire sinon le checkout écrase les fichiers unstashés.

**Build & Push Docker** -- build `--target prod`, tag `${BUILD_NUMBER}` pour versioning immuable. Chaque build produit une image distincte -- rollback possible à tout moment.

**Deploy Secrets** *(main)* -- met à jour les secrets K8s depuis les credentials Jenkins :

| Stage | Secret K8s | Credentials Jenkins |
|---|---|---|
| JWT | `jwt-keys` | `jwt-private-key`, `jwt-public-key` |
| OAuth | `oauth-secrets` | `GOOGLE_CLIENT_ID/SECRET`, `FACEBOOK_CLIENT_ID/SECRET` |
| MariaDB exporter | `mysqld-exporter-secret` | `MYSQLD_EXPORTER_PASSWORD` |
| Backup | `restic-secret`, `rclone-config` | `RESTIC_PASSWORD`, `rclone-config` |

**Deploy to Kubernetes** *(main)* -- `kubectl apply -k k8s/` + `set image` + attente du rollout (timeout 5 min).

**Database Migrations** *(main)* -- `doctrine:schema:update --force` + `cache:clear` via `kubectl exec` dans le pod de prod.

> À terme, migrer vers les migrations Doctrine classiques pour un historique versionné.

**Health Check** -- `curl` sur `https://api.the-tip-top.jonathanlore.fr/api` jusqu'à HTTP 200, timeout 2 min.

**Post** -- suppression de l'image Docker locale + notification Discord (succès ou échec).

---

## Couverture de tests

| Classe / Endpoint | Tests | Type | Ce qui est vérifié |
|---|---|---|---|
| `CodeValidationProcessor` | 6 | Unitaire (mocks) | user non connecté, rate limit, code inexistant/validé/expiré, cas nominal |
| `CodeClaimProcessor` | 4 | Unitaire (mocks) | code inexistant, non validé, déjà claimed, cas nominal |
| `POST /api/login` | 4 | Fonctionnel (MariaDB) | credentials valides → JWT, mauvais mdp, email inconnu, champ manquant |
| `POST /api/codes/validate` | 5 | Fonctionnel (MariaDB) | sans token, code valide, déjà validé, expiré, inexistant |

Les tests fonctionnels recrèent le schéma et rechargent les fixtures avant chaque test via `setUp()`.

---

## Fichiers clés

```
api/
├── Jenkinsfile
├── phpunit.xml.dist                          ← 2 suites : Unit, Functional
├── k8s/jenkins/php-test-pod.yaml             ← pod PHP + sidecar MariaDB
├── tests/
│   ├── bootstrap.php                         ← chargement env + DG\BypassFinals
│   ├── Fixtures/TestFixtures.php             ← fixtures groupe "test"
│   ├── Unit/State/
│   │   ├── CodeValidationProcessorTest.php
│   │   └── CodeClaimProcessorTest.php
│   └── Functional/
│       ├── ApiTestTrait.php                  ← helpers partagés (getJwtToken...)
│       ├── LoginTest.php
│       └── CodeValidationTest.php
└── config/
    ├── services_test.yaml                    ← déclaration TestFixtures (test only)
    └── packages/rate_limiter.yaml            ← policy: no_limit en when@test
```

---
