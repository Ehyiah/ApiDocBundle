# Exemple de Fusion YAML + PHP

Ce dossier contient un exemple concret montrant comment la documentation YAML et PHP sont fusionnées.

## Fichiers de l'exemple

### 1. YAML Configuration
📄 [products.yaml](products.yaml)

Définit:
- **GET `/api/products`** - Liste paginée des produits
- **Schema `Product`** - Définition du modèle Product

### 2. PHP Configuration Class
📄 [ProductApiDocConfig.php](ProductApiDocConfig.php)

Ajoute:
- **POST `/api/products`** - Créer un produit
- **GET `/api/products/{id}`** - Obtenir un produit
- **PUT `/api/products/{id}`** - Modifier un produit
- **DELETE `/api/products/{id}`** - Supprimer un produit
- **Schema `Category`** - Nouveau composant Category

## Résultat de la fusion

Lorsque ces deux sources sont chargées, le bundle les fusionne automatiquement pour produire:

```yaml
paths:
  /api/products:
    get:                    # ← De YAML
      operationId: listProducts
      summary: List all products
      tags: [Products]
      # ... paramètres, réponses
    
    post:                   # ← De PHP
      operationId: createProduct
      summary: Create a new product
      tags: [Products]
      # ... request body, réponses
  
  /api/products/{id}:       # ← De PHP
    get:
      operationId: getProduct
      summary: Get product by ID
      # ...
    
    put:
      operationId: updateProduct
      summary: Update a product
      # ...
    
    delete:
      operationId: deleteProduct
      summary: Delete a product
      # ...

components:
  schemas:
    Product:                # ← De YAML
      type: object
      properties:
        id:
          type: integer
        name:
          type: string
        price:
          type: number
        # ...
    
    Category:               # ← De PHP
      type: object
      properties:
        id:
          type: integer
        name:
          type: string
        slug:
          type: string
      # ...
```

## Comment utiliser cet exemple

### Option 1: Tester dans votre projet

1. **Copiez le fichier YAML** dans votre répertoire source:
   ```bash
   cp docs/examples/products.yaml src/Swagger/products.yaml
   ```

2. **Copiez la classe PHP** dans votre projet:
   ```bash
   mkdir -p src/ApiDoc
   cp docs/examples/ProductApiDocConfig.php src/ApiDoc/
   ```

3. **Enregistrez la classe** dans `config/services.yaml`:
   ```yaml
   services:
       _instanceof:
           Ehyiah\ApiDocBundle\Config\ApiDocConfigInterface:
               tags: ['ehyiah_api_doc.config_provider']
   ```

4. **Visualisez le résultat** sur `/ehyiah/api/doc`

### Option 2: Générer la doc complète

Utilisez la commande de génération pour voir le résultat fusionné:

```bash
bin/console apidocbundle:api-doc:generate
```

Cela créera un fichier `openapi.yaml` dans `src/Swagger/dump/` contenant:
- Toutes les routes YAML
- Toutes les routes PHP
- Tous les schémas des deux sources

## Avantages de cette approche

### ✅ Division des responsabilités
- **YAML** = Documentation statique, schemas réutilisables
- **PHP** = Documentation dynamique, routes CRUD générées

### ✅ Pas de duplication
- Définissez le schema `Product` une seule fois en YAML
- Réutilisez-le dans les routes PHP via `->ref('#/components/schemas/Product')`

### ✅ Évolutif
- Ajoutez de nouvelles routes sans toucher au YAML
- Modifiez les schemas YAML sans toucher au PHP
- Les deux sources restent synchronisées automatiquement

## Cas d'usage réels

### Scénario 1: Migration progressive
```
Étape 1: Toute la doc en YAML
Étape 2: Migrez progressivement vers PHP
Étape 3: Conservez YAML pour les schemas, PHP pour les routes
```

### Scénario 2: Team workflow
```
Team Backend: Édite les fichiers YAML (commits Git)
Développeur principal: Génère les routes CRUD via PHP
```

### Scénario 3: Documentation générée
```php
// Générez automatiquement la doc depuis vos entités Doctrine
foreach ($entities as $entity) {
    $builder->addRoute()
        ->path("/api/{$entity->getName()}")
        ->method('GET')
        // ...
}
```

## Vérification de la fusion

Pour déboguer et voir exactement ce qui est fusionné:

```php
// Dans ApiDocController::loadConfigFiles()
$yamlConfig = LoadApiDocConfigHelper::loadApiDocConfig(...);
$phpConfig = $this->loadApiDocConfigHelper->loadPhpConfigDoc();

// Inspectez avant fusion
dump($yamlConfig);  // Debug YAML
dump($phpConfig);   // Debug PHP

$merged = array_merge_recursive($yamlConfig, $phpConfig);
dump($merged);      // Debug résultat
```

## Notes importantes

### ⚠️ Conflits potentiels

Si vous définissez **la même route** (path + method) dans YAML ET PHP:

```yaml
# YAML
/api/products:
  get:
    summary: "Liste des produits"
```

```php
// PHP
->path('/api/products')->method('GET')
->summary('Get all products')
```

Résultat: `array_merge_recursive` fusionnera les deux définitions.
Les valeurs scalaires de PHP écraseront celles de YAML.

**Recommandation**: Évitez les doublons - utilisez soit YAML soit PHP pour une route donnée.

### ✅ Bonne pratique

**Schémas** → YAML (réutilisables, version control friendly)
**Routes** → PHP (génération dynamique, type-safe)

```yaml
# schemas.yaml - Définitions réutilisables
components:
  schemas:
    Product: { ... }
    Category: { ... }
    User: { ... }
```

```php
// Routes auto-générées en PHP
foreach ($resources as $resource) {
    $this->addCrudRoutes($builder, $resource);
}
```

## Résumé

| Source | Fichier | Contenu | Utilisation |
|--------|---------|---------|-------------|
| YAML | `products.yaml` | GET /api/products + Schema Product | Doc statique |
| PHP | `ProductApiDocConfig.php` | POST/GET/PUT/DELETE + Schema Category | Doc dynamique |
| **Résultat** | **Swagger UI** | **Routes complètes CRUD + Tous les schemas** | **API Doc finale** |

La fusion est **automatique** et **transparente** ! 🎉
