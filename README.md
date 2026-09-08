# SocialApp

A PHP & MySQL social web application with authentication, friend connections, activity feeds, and direct messaging.

## Features
* **Auth & Profiles:** Registration, login, session control, and profile customization.
* **Social:** Friend requests, user directory, and interactive post/comment feeds.
* **Messaging:** Real-time direct messaging via AJAX polling.

## Database Documentation
![Entity-Relationship Diagram (ERD)](docs/ER%20diagram.png)
![Relational Schema](docs/Relational%20Schema.png)

## Quick Setup
1. Move project to your web server root (e.g., `htdocs/SocialApp`).
2. Import `sql/schema.sql` into MySQL.
3. Add your connection credentials to `config/db.php`.
4. Open `http://localhost/SocialApp/public/login.php` in your browser.
