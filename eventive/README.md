# 🎓 Smart Event Management System

A web-based system for managing college events end to end: event creation, student registration, attendance, budget and expense tracking, feedback, analytics and event recommendations. Built with **HTML, CSS, JavaScript and PHP**, on an **Oracle** database.

---

## ✨ Features

**Student portal**
- Log in with Student ID + Date of Birth
- Browse and search events, view event details
- Register for events (an `ABSENT` attendance row is created automatically)
- "My Events" with attendance status
- Submit star-rating + comment feedback (one per event)
- Event recommendations

**Admin portal**
- Separate admin login (email + password)
- Dashboard overview (events, students, registrations, popular events, category split)
- Add, update and delete events (Event ID such as `MA001` is auto-generated)
- Mark attendance (Present/Absent) for each registered student, saved in bulk
- View all registrations
- Track expenses against each event's allocated budget (overspent / warning / ok)
- Event analytics (attendance score, budget efficiency, success score) and feedback analysis

**Database automation (Oracle triggers)**
- Analytics row auto-created when an event is inserted
- Attendance score recalculated when attendance changes
- Budget efficiency recalculated when an expense is added
- Success score = combination of attendance score and budget efficiency

---

## 🗂️ Project structure

```
proj/
├── assets/
│   ├── css/style.css            # Shared styles
│   └── javascript/app.js        # API helper, session, toasts, shared utilities
├── backend/                     # PHP REST-style JSON endpoints (OCI8)
│   ├── db_connect.php           # DB credentials + connection + CORS headers
│   ├── login.php                # Student / admin login
│   ├── events.php               # List events / single event details
│   ├── add_event.php            # Add / update / delete event (admin)
│   ├── register.php             # Student registers for an event
│   ├── my_events.php            # A student's registrations
│   ├── mark_attendance.php      # Save attendance (single or bulk)
│   ├── add_expense.php          # Add an expense (triggers analytics update)
│   ├── get_budget.php           # Budget usage per event
│   ├── feedback.php             # Submit feedback
│   ├── get_analytics.php        # Overview / popular / feedback / category / attendance list
│   ├── recommendations.php      # Recommended events for a student
│   ├── get_venues.php           # Dropdown data
│   ├── get_categories.php       # Dropdown data
│   ├── get_students.php         # Dropdown data
│   └── get_coordinators.php     # Dropdown data (student coordinators)
├── frontend/
│   ├── login.html
│   ├── student_dashboard.html
│   ├── events.html
│   ├── recommendations.html
│   └── admin_dashboard.html
└── story.txt                    # Project synopsis and design notes
```

---

## 🧰 Requirements

| Component | Notes |
|---|---|
| Web server with PHP 7.4+ | XAMPP / WAMP / Apache |
| **PHP OCI8 extension** | Enable `extension=oci8_12c` (or your version) in `php.ini` |
| Oracle Database | Oracle XE 11g/18c/21c is fine |
| Oracle Instant Client | Needed by OCI8 if the DB is not on the same machine |
| A modern browser | Chrome / Edge / Firefox |

---

## 🚀 Setup

### 1. Get the code
```bash
git clone https://github.com/<your-username>/<your-repo>.git
```
Copy the `proj` folder into your web root, e.g. `C:\xampp\htdocs\proj`.

### 2. Create the database
Create the tables from `story.txt` (Admin, Student, Department, Coordinator, Event, EventCategory, Venue, Registration, Attendance, Expense, Feedback, Event_Analytics, Student_Interest, Volunteer_Assignment, Skill) in your Oracle schema.

You also need these **sequences** (used by the PHP code):

```sql
CREATE SEQUENCE ATT_SEQ     START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE EXPENSE_SEQ START WITH 1 INCREMENT BY 1;
```

and the triggers described in `story.txt` (auto-create analytics, attendance score, budget efficiency, success score). `Attendance.Status` should default to `'ABSENT'`.

Add some seed data: at least one row in `ADMIN`, `EVENTCATEGORY`, `VENUE`, `STUDENT`, and `COORDINATOR` (a coordinator must be a student).

### 3. Configure the connection
Edit `backend/db_connect.php`:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '1521');
define('DB_SID',  'XE');        // your SID / service name
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');
```

> ⚠️ Do not commit real credentials. Keep your local `db_connect.php` out of version control (see *Security notes*).

### 4. Check the API base URL
`assets/javascript/app.js`:
```js
const API_BASE = 'http://localhost/proj/backend';
```
Change it if you put the project in a different folder.

### 5. Run
Start Apache, then open:

```
http://localhost/proj/frontend/login.html
```

- **Student:** Student ID + Date of Birth
- **Admin:** email + password from the `ADMIN` table

---

## 🔌 API overview

All endpoints return JSON with `success: true|false`.

| Endpoint | Method | Purpose |
|---|---|---|
| `login.php` | POST | `{role, student_id, dob}` or `{role, email, password}` |
| `events.php` | GET | All events, or `?event_id=` for one |
| `add_event.php` | POST | `{action: add/update/delete, event_name, event_date, venue_id, category_id, budget, coordinator_id}` |
| `register.php` | POST | `{student_id, event_id}` |
| `my_events.php` | GET | `?student_id=` |
| `mark_attendance.php` | POST | `{event_id, records:[{student_id, status}]}` or a single `{event_id, student_id, status}` |
| `add_expense.php` | POST | `{event_id, amount}` |
| `get_budget.php` | GET | Budget allocated / used / remaining per event |
| `feedback.php` | POST | `{student_id, event_id, rating, comments}` |
| `get_analytics.php` | GET | `?type=overview \| analytics \| popular \| feedback \| category_dist \| attendance_list&event_id=` |
| `recommendations.php` | GET | `?student_id=` |

---

## 🔒 Security notes (read before deploying)

This is a college project and is **not production-ready** as is:

- Admin passwords are stored and compared in **plain text**. Use `password_hash()` / `password_verify()`.
- Backend endpoints do not check the PHP session, so anyone who knows the URL can call admin endpoints. Add a role check at the top of admin endpoints.
- `Access-Control-Allow-Origin: *` is open to every site. Restrict it to your own origin.
- `db_connect.php` and `login.php` return raw database error text. Remove those lines in production.
- Move DB credentials to environment variables or an untracked config file, and add it to `.gitignore`.

---

## 🛠️ Troubleshooting

| Problem | Fix |
|---|---|
| `Call to undefined function oci_connect()` | Enable the OCI8 extension in `php.ini` and restart Apache |
| `ORA-12541: no listener` | Start the Oracle listener / check host, port and SID |
| `ORA-02289: sequence does not exist` | Create `ATT_SEQ` and `EXPENSE_SEQ` |
| "Network error" in the browser | Check `API_BASE`, and that Apache is running |
| `ORA-02291` when adding an event | Venue, category or coordinator ID does not exist |

---

## 📌 Roadmap

- Interest-based recommendations (use `STUDENT_INTEREST`)
- Volunteer assignment by skill
- Hashed passwords + server-side role checks
- `analytics.html` page

---

## 📄 License

Add a license of your choice (e.g. MIT) before publishing.
