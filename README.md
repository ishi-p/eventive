# 🎓 Eventive- A Smart Event Management System

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
    ├── login.html
    ├── student_dashboard.html
    ├── events.html
    ├── recommendations.html
    └── admin_dashboard.html

