# Database Migrations and Seeds

This directory contains database migrations and seed data for the Improtřesk 2026 application.

## Structure

```
database/
├── migrations/         # SQL migration files
├── seeds/             # SQL seed data files
├── migrate.php        # Migration runner script
└── seed.php           # Seeder runner script
```

## Usage

### Running Migrations

To create all database tables:

```bash
php database/migrate.php
```

This will execute all SQL files in the `migrations/` directory in order.

### Running Seeders

To populate the database with sample data:

```bash
php database/seed.php
```

This will execute all SQL files in the `seeds/` directory in order.

### Quick Setup

To set up the complete database from scratch:

```bash
# Run migrations
php database/migrate.php

# Run seeders
php database/seed.php
```

## Migrations

### 001_create_users_table.sql
Creates the users table for authentication.

**Fields:**
- id (primary key)
- name
- email (unique)
- password (hashed)
- role (user/admin)
- phone
- created_at, updated_at

### 002_create_workshops_table.sql
Creates the workshops table for festival workshops.

**Fields:**
- id (primary key)
- name
- description
- instructor
- date, time, duration_minutes
- price
- capacity
- location
- level (beginner/intermediate/advanced/all)
- is_active
- created_at, updated_at

### 003_create_registrations_table.sql
Creates the registrations table linking users to workshops.

**Fields:**
- id (primary key)
- user_id (foreign key)
- workshop_id (foreign key)
- payment_status (pending/paid/cancelled/refunded)
- companion_program (boolean)
- variable_symbol (unique payment identifier)
- paid_at
- notes
- created_at, updated_at

### 004_create_program_items_table.sql
Creates the program_items table for festival schedule.

**Fields:**
- id (primary key)
- title
- description
- performer
- type (performance/workshop/discussion/party/other)
- date, start_time, end_time
- location
- is_free
- max_capacity
- image_url
- is_active
- created_at, updated_at

### 005_create_faq_table.sql
Creates the FAQ table for frequently asked questions.

**Fields:**
- id (primary key)
- question
- answer
- category
- order
- is_active
- created_at, updated_at

### 006_create_password_resets_table.sql
Creates the password_resets table for password recovery tokens.

**Fields:**
- id (primary key)
- email
- token
- created_at

## Seeds

### 001_seed_admin_user.sql
Creates default users:
- Admin user: admin@improtresk.cz / password
- Test user: user@improtresk.cz / password

### 002_seed_workshops.sql
Creates sample workshops with various levels and instructors.

### 003_seed_program_items.sql
Creates sample festival program items (performances, discussions, parties).

### 004_seed_faq.sql
Creates sample FAQ items in various categories.

## Environment Variables

Make sure your `.env` file has correct database credentials:

```env
DB_HOST=127.0.0.1
DB_NAME=improtresk
DB_USER=imp
DB_PASS=rotresk
```

## Notes

- All tables use InnoDB engine and utf8mb4 charset
- Foreign keys have CASCADE delete for data integrity
- Indexes are created on frequently queried columns
- Migrations use `IF NOT EXISTS` to prevent errors on re-run
- Seeders use `ON DUPLICATE KEY UPDATE` to allow re-running
