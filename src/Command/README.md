# Liste des commandes du projet

## CreateCodesCommand

Génère les 500 000 codes alphanumériques uniques et les insère en base de données de manière optimisée pour le gros volume.

```bash
php bin/console app:create:codes
```
Utilisation de Doctrine\DBAL\Connection au lieu de l'ORM (EntityManager) pour éviter la surcharge mémoire liée au suivi des 500 000 entités.

-> __En cas de crash mémoire en prod__

__Option 1__ : Réduire la RAM allouée  
```php
ini_set('memory_limit', '1024M');
```

__Option 2__ : Réduire la taille du lot (Batch Size) 

`self::BATCH_SIZE = 500` peut être trop élevé pour la RAM disponible.
