# 🌳 Arbre Généalogique

Application web développée avec **Symfony** permettant de créer et gérer un arbre généalogique dynamique : personnes, générations personnalisées et liens familiaux (parents, enfants, conjoints).

Ce projet est un **projet personnel** visant à pratiquer Symfony, Doctrine ORM et la modélisation de données relationnelles.

---

## 🚀 Fonctionnalités

- Création et gestion des **personnes**
- Gestion des **générations personnalisées** (nom + ordre unique)
- Liaisons familiales :
  - père / mère
  - enfants
  - partenaires (conjoints)
- Formulaires Symfony avec validation
- Suppression sécurisée avec nettoyage des relations
- Interface CRUD simple et claire

---

## 🛠️ Technologies utilisées

- **PHP 7.4+ / 8.x**
- **Symfony**
- **Doctrine ORM**
- **PostgreSQL**
- **Twig**
- **Bootstrap** (ou autre CSS si applicable)

---

## 📦 Prérequis

- PHP >= 7.4
- Composer
- PostgreSQL
- Symfony CLI (optionnel mais recommandé)

---

## ⚙️ Installation

### 1️⃣ Cloner le projet
```bash
git clone https://github.com/ton-username/arbre-genealogique.git
cd arbre-genealogique
````

### 2️⃣ Installer les dépendances

```bash
composer install
```

### 3️⃣ Configuration de l’environnement

Copie le fichier `.env` et adapte-le si besoin, puis crée un `.env.local` :

```env
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/arbre?serverVersion=15"
```

### 4️⃣ Créer la base de données

```bash
php bin/console doctrine:database:create
```

### 5️⃣ Appliquer les migrations

```bash
php bin/console doctrine:migrations:migrate
```

---

## ▶️ Lancer le serveur

```bash
symfony server:start
```

Puis : [http://127.0.0.1:8000](http://127.0.0.1:8000)


---

## 🧠 Modèle de données (simplifié)

* **Personne**

  * prénom
  * nom
  * date de naissance
  * genre
  * génération (nom + ordre)
  * père / mère
  * enfants
  * partenaires

* **Generation**

  * nom
  * ordre (unique)

---

## 📌 Bonnes pratiques appliquées

* User PostgreSQL dédié au projet
* Migrations Doctrine
* Contraintes d’unicité en base
* Validation Symfony
* Séparation logique Form / Controller / Entity

---

## 👤 Auteur

Projet développé par **Mélène**

💻 Projet personnel Symfony / apprentissage