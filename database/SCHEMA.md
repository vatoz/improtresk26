# Database Schema

## Entity Relationship Diagram

```
┌─────────────────┐
│     users       │
├─────────────────┤
│ id (PK)         │
│ name            │
│ email (UNIQUE)  │
│ password        │
│ role            │
│ phone           │
│ created_at      │
│ updated_at      │
└────────┬────────┘
         │
         │ 1:N
         │
         ▼
┌─────────────────┐         ┌──────────────────┐
│ registrations   │   N:1   │   workshops      │
├─────────────────┤◄────────┤──────────────────┤
│ id (PK)         │         │ id (PK)          │
│ user_id (FK)    │         │ name             │
│ workshop_id (FK)│         │ description      │
│ payment_status  │         │ instructor       │
│ companion_program│        │ date             │
│ variable_symbol │         │ time             │
│ paid_at         │         │ duration_minutes │
│ notes           │         │ price            │
│ created_at      │         │ capacity         │
│ updated_at      │         │ location         │
└─────────────────┘         │ level            │
                            │ is_active        │
                            │ created_at       │
                            │ updated_at       │
                            └──────────────────┘

┌──────────────────┐         ┌──────────────────┐
│ program_items    │         │ faq              │
├──────────────────┤         ├──────────────────┤
│ id (PK)          │         │ id (PK)          │
│ title            │         │ question         │
│ description      │         │ answer           │
│ performer        │         │ category         │
│ type             │         │ order            │
│ date             │         │ is_active        │
│ start_time       │         │ created_at       │
│ end_time         │         │ updated_at       │
│ location         │         └──────────────────┘
│ is_free          │
│ max_capacity     │         ┌──────────────────┐
│ image_url        │         │ password_resets  │
│ is_active        │         ├──────────────────┤
│ created_at       │         │ id (PK)          │
│ updated_at       │         │ email            │
└──────────────────┘         │ token            │
                             │ created_at       │
                             └──────────────────┘
```

## Table Descriptions

### users
Stores user accounts for authentication and profile management.

**Key Relationships:**
- One user can have many registrations (1:N)

**Indexes:**
- `idx_email` on email (for login)
- `idx_role` on role (for admin checks)

---

### workshops
Workshop offerings during the festival.

**Key Relationships:**
- One workshop can have many registrations (1:N)

**Indexes:**
- `idx_date` on date (for listing)
- `idx_active` on is_active (for filtering)

---

### registrations
Links users to workshops they've registered for, tracks payment status.

**Key Relationships:**
- Many-to-one with users (N:1)
- Many-to-one with workshops (N:1)
- Unique constraint on (user_id, workshop_id) - one user can only register once per workshop

**Indexes:**
- `idx_payment_status` on payment_status (for admin filtering)
- `idx_variable_symbol` on variable_symbol (for payment matching)

**Cascade Rules:**
- ON DELETE CASCADE for both foreign keys (if user or workshop deleted, registration is removed)

---

### program_items
Festival schedule items (performances, discussions, parties, etc.)

**Key Relationships:**
- None (standalone table)

**Indexes:**
- `idx_date_time` on (date, start_time) (for chronological listing)
- `idx_type` on type (for filtering by type)
- `idx_active` on is_active (for filtering)

---

### faq
Frequently asked questions and answers.

**Key Relationships:**
- None (standalone table)

**Indexes:**
- `idx_category` on category (for grouping)
- `idx_active` on is_active (for filtering)
- `idx_order` on order (for sorting)

---

### password_resets
Temporary tokens for password reset functionality.

**Key Relationships:**
- None (email links to users.email logically, not via FK)

**Indexes:**
- `idx_email` on email (for lookup)
- `idx_token` on token (for validation)
- `idx_created` on created_at (for cleanup of old tokens)

## Enums

### users.role
- `user` - Regular user
- `admin` - Administrator with full access

### workshops.level
- `beginner` - For beginners
- `intermediate` - Requires some experience
- `advanced` - For experienced improvisers
- `all` - Suitable for all levels

### registrations.payment_status
- `pending` - Awaiting payment
- `paid` - Payment received
- `cancelled` - Registration cancelled
- `refunded` - Payment refunded

### program_items.type
- `performance` - Improvisation show
- `workshop` - Workshop session
- `discussion` - Discussion or Q&A
- `party` - Social event
- `other` - Other event type

## Data Integrity

1. **Foreign Keys**: All foreign keys use CASCADE delete to maintain referential integrity
2. **Unique Constraints**:
   - users.email
   - registrations.(user_id, workshop_id)
   - registrations.variable_symbol
3. **Default Values**: Timestamps, booleans, and enums have sensible defaults
4. **Character Set**: All tables use utf8mb4 for full Unicode support
