# Filigrane — raccourcis de développement.
#
#   make up        démarre l'environnement local
#   make install   première installation complète
#   make test      joue toute la suite de tests
#   make deploy    met en ligne

SHELL := /bin/bash
APP := docker compose exec -T app

.DEFAULT_GOAL := aide

.PHONY: aide up down restart shell logs install build assets migrate fresh test test-php test-e2e captures apercus icones images-test deploy deploy-assets sauvegarde

aide: ## Affiche cette aide
	@grep -E '^[a-zA-Z-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

up: ## Démarre les conteneurs
	docker compose up -d
	@echo "→ http://localhost:8080   (courriels : http://localhost:8025)"

down: ## Arrête les conteneurs
	docker compose down

restart: ## Redémarre les conteneurs
	docker compose restart

shell: ## Ouvre un shell dans le conteneur PHP
	docker compose exec app bash

logs: ## Suit les journaux
	docker compose logs -f app

install: ## Installation complète depuis zéro
	docker compose build app
	docker compose up -d
	@sleep 5
	test -f .env || cp .env.example .env
	$(APP) composer install
	$(APP) php artisan key:generate
	$(APP) php artisan storage:link
	$(APP) php artisan migrate:fresh --seed --force
	$(APP) php artisan filament:assets
	$(APP) php scripts/generer-images-test.php
	npm install
	npm run build
	npx playwright install chromium
	node scripts/generer-icones.mjs
	$(APP) php scripts/generer-favicon-ico.php
	@echo "→ http://localhost:8080 — lise@filigrane.test / filigrane"

build: ## Reconstruit l'image Docker
	docker compose build app

assets: ## Recompile les feuilles de style et les scripts
	npm run build

migrate: ## Applique les migrations
	$(APP) php artisan migrate

fresh: ## Remet la base à zéro et la réamorce
	$(APP) php artisan migrate:fresh --seed --force
	rm -rf storage/app/private/photos storage/app/public/photos

icones: ## Régénère les icônes de l'application
	node scripts/generer-icones.mjs
	$(APP) php scripts/generer-favicon-ico.php

images-test: ## Régénère les images de test
	$(APP) php scripts/generer-images-test.php

test: test-php test-e2e ## Joue toute la suite

test-php: ## Tests PHP (chaîne de traitement des images, numérotation)
	$(APP) php artisan test

test-e2e: ## Tests de bout en bout (Playwright)
	npx playwright test

captures: ## Régénère les captures d'écran de la documentation
	npx playwright test captures --project=mac
	npx playwright test captures --project=mobile || true

apercus: ## Régénère les aperçus de la page publique « À propos »
	$(APP) php artisan filigrane:jeu-d-essai
	node scripts/apercus.mjs

deploy: ## Met en ligne sur lise.dananlab.fr
	./scripts/deployer.sh

sauvegarde: ## Sauvegarde la base et les photographies en ligne
	./scripts/sauvegarder.sh
