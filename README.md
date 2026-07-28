# Final Year Project Management Portal

React frontend + Laravel 13 API for managing the full FYP lifecycle (students, teachers, proposals, progress, and evaluations).

## Stack

- **Frontend:** React (Vite + TypeScript), Tailwind CSS, React Router
- **Backend:** Laravel 13, Sanctum (API tokens), SQLite (local default)
- **Roles:** Admin, Teacher (Supervisor), Student

## Quick start

### Backend

```bash
cd backend
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

API runs at `http://127.0.0.1:8000`.

### Frontend

```bash
cd frontend
npm install
npm run dev
```

App runs at `http://localhost:5173` and opens on the **login page**.

## Demo accounts

Password for all: `password`

| Role    | Email               |
|---------|---------------------|
| Admin   | admin@fyp.local     |
| Teacher | teacher@fyp.local   |
| Student | student@fyp.local   |

## API endpoints (Phase 1)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/login` | Login |
| GET | `/api/auth/me` | Current user |
| POST | `/api/auth/logout` | Logout |
| POST | `/api/auth/change-password` | Change password |
| GET | `/api/dashboard` | Role-based dashboard |

## Project structure

```
finalyear/
├── backend/          # Laravel API
├── frontend/         # React SPA
└── studentportal.md  # Product specification
```

## Development roadmap

Aligned with `studentportal.md`:

1. **Phase 1 (done):** Auth, roles, departments, teachers, students, supervisor assignment, dashboards
2. **Phase 2:** Proposal submission, review, comments, versioning, notifications
3. **Phase 3:** Progress reports, milestones, meetings, calendar
4. **Phase 4:** Final submission, evaluation, reports
5. **Phase 5:** AI features and showcase
