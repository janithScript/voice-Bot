
<img width="949" height="439" alt="home" src="https://github.com/user-attachments/assets/345c4efa-d942-4774-b7db-8628bd7cc729" />

# 🏥 Clinic VoiceBot

A full-stack Clinic Appointment Booking System powered by **Laravel (Backend)** and a **Python Flask VoiceBot Service**. This project enables users to book appointments using voice interactions and manage clinic operations efficiently.

---

##  Features

###  VoiceBot (Python - Flask)

* Speech recognition-based appointment booking
* Converts user voice to text
* Communicates with Laravel backend via API
* Simple and extensible Flask architecture

### Backend (Laravel)

* Appointment management system
* Doctor management
* Database seeding with demo data
* RESTful API for VoiceBot integration
* Secure authentication-ready structure

---

##  System Architecture

```
User (Voice Input)
        ↓
Python Flask VoiceBot
        ↓ (API Calls)
Laravel Backend (REST API)
        ↓
MySQL Database
```

---

##  Project Structure

```
clinic-voicebot/
│
├── voicebot-python/      # Flask VoiceBot service
│   ├── app.py
│   ├── requirements.txt
│   └── ...
│
├── laravel-backend/      # Laravel application
│   ├── app/
│   ├── routes/
│   ├── database/
│   └── ...
│
└── README.md
```

---

##  Getting Started

###  Prerequisites

Make sure you have installed:

* PHP >= 8.1
* Composer
* Python >= 3.8
* MySQL
* Node.js (optional for frontend assets)

---

##  Installation & Setup

### 1️ Clone the Repository

```bash
git clone https://github.com/janithScript/voice-Bot.git
cd voice-Bot
```

---

##  Setup Python VoiceBot

###  Navigate to VoiceBot Directory

```bash
cd voicebot-python

pip install -r requirements.txt
```

###  Run Flask Server

```bash
python app.py
```

> The Flask service will start (default: [http://127.0.0.1:5000](http://127.0.0.1:5000))

---

##  Setup Laravel Backend

###  Navigate to Backend Directory

```bash
cd laravel-backend
```

### Install Dependencies

```bash
composer install
```

### Generate Application Key

```bash
php artisan key:generate
```

### Configure Environment

Update your `.env` file with database credentials:

```
DB_DATABASE=clinic_voicebot
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Run Migrations & Seed Data

```bash
php artisan migrate --seed
```

> This command will:
>
> * Run all database migrations
> * Populate database with sample data (doctors, etc.)

###  Start Laravel Server

```bash
php artisan serve
```

> Laravel will run on: [http://127.0.0.1:8000](http://127.0.0.1:8000)


