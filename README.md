# Unity University

Unity University is a Laravel 13 + Livewire 4 campus management platform built with Flux UI, Tailwind CSS 4, and Spatie Laravel Permission.

## Overview

The platform provides a central place to manage:

- Departments
- Subjects
- Faculty profiles
- Classes
- Enrollments
- Role-based access control
- Dashboard analytics

It includes seeded sample data so you can open the app in the browser and test the workflow immediately.

## Tech Stack

- PHP 8.4
- Laravel 13
- Livewire 4
- Livewire Flux UI 2
- Tailwind CSS 4
- Spatie Laravel Permission
- SQLite by default

## Features

- Branded dashboard with analytics cards and trends
- Clickable breadcrumbs across management pages
- Department management with create, edit, delete safeguards
- Subject management with full CRUD
- Faculty profile management with role assignment
- Class management with edit/update and enrollment protection
- Enrollment workflow for students
- Centralized authorization policies
- Toast-style feedback for success and error states

## Roles

The app uses role-based access control with these roles:

- Admin
- DepartmentStaff
- Faculty
- Student

## Sample Accounts

After seeding, you can use these credentials:

- Admin: `admin@university.edu` / `password`
- Department staff: `deptstaff@university.edu` / `password`
- Faculty accounts: `alice@university.edu`, `bob@university.edu`, and others / `password`
- Student accounts: `student1@university.edu` through `student30@university.edu` / `password`

## Getting Started

### 1. Install dependencies

```bash
composer install
npm install
```

### 2. Configure the environment

Copy the example environment file if needed:

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Prepare the database

This project uses SQLite by default.

```bash
touch database/database.sqlite
php artisan migrate:fresh --seed
```

### 4. Build frontend assets

```bash
npm run build
```

### 5. Start the application

You can run the app with the combined dev script:

```bash
composer run dev
```

Or run the processes separately:

```bash
php artisan serve
npm run dev
```

## Main Routes

- `/dashboard` - analytics dashboard
- `/departments` - department management
- `/subjects` - subject management
- `/faculty` - faculty management
- `/classes` - class management
- `/enrollments` - enrollment workflow

## Testing

Run the test suite with:

```bash
php artisan test
```

For the current management features, targeted feature tests are available under `tests/Feature/`.

## Notes

- The app is set up with seeded data for browser testing.
- Dashboard metrics are driven from live database records.
- Access to management pages is restricted to authorized roles.
