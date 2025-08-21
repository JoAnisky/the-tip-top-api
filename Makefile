# Lancer le projet Symfony en dev
up-dev:
	docker compose --env-file .docker/.env.docker.dev up -d --build

# Lancer le projet Symfony en prod
up-prod:
	docker compose --env-file .docker/.env.docker.prod up -d --build

# Stopper et nettoyer en dev
down-dev:
	docker compose --env-file .docker/.env.docker.dev down -v

# Stopper en prod
down-prod:
	docker compose --env-file .docker/.env.docker.prod down

# Afficher les logs du conteneur app en dev
logs-dev:
	docker compose --env-file .docker/.env.docker.dev logs -f app

# Afficher les logs du conteneur app en prod
logs-prod:
	docker compose --env-file .docker/.env.docker.prod logs -f app