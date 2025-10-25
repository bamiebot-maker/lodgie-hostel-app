# 🏠 Lodgie - Hostel Booking & Reservation System

**Lodgie** is a complete, responsive web-based hostel booking system built with Core PHP, MySQL, and Bootstrap 5. It provides a modern, clean, and easy-to-use platform for landlords to list properties, tenants to find and book accommodation, and admins to manage the entire ecosystem.

![Lodgie Logo](httpsVITE_API_KEY/assets/images/logo.png) ---

## 🚀 Project Overview

Lodgie connects students/tenants with hostel owners (landlords). It features a role-based system with three distinct user types, each with a dedicated dashboard and functionalities.

* **Tenant / Student:** Can browse, search, book, and pay for hostels. They can also manage their bookings and leave reviews.
* **Landlord:** Can post, manage, and update their hostel listings, upload photos, and view/approve incoming booking requests.
* **Admin:** Has super-user privileges to manage all users, hostels, bookings, payments, and system settings.

## ✨ Core Features

* **Responsive Design:** Fully responsive UI built with Bootstrap 5.
* **Role-Based Access Control:** Separate dashboards and permissions for Admins, Landlords, and Tenants.
* **Secure Authentication:** Secure login and registration with password hashing (`password_hash()`).
* **Hostel Management:** Full CRUD functionality for landlords to manage their properties.
* **Booking System:** A seamless workflow for tenants to book and pay.
* **Payment Integration:** Integrated with **Paystack** for secure online payments.
* **Notification System:** Real-time notifications for bookings, payments, and approvals.
* **Review & Rating System:** Tenants can rate and review hostels after their stay.
* **Admin Panel:** Powerful dashboard for complete system management.
* **Secure Code:** Uses PDO prepared statements, CSRF tokens, and input sanitization to prevent common vulnerabilities.

---

## 🧰 Tech Stack

* **Backend:** Core PHP (Procedural & OOP mix)
* **Database:** MySQL (with PDO)
* **Frontend:** HTML5, CSS3, JavaScript
* **UI Framework:** Bootstrap 5
* **Payment Gateway:** Paystack
* **Icons:** Bootstrap Icons

---

## ⚙️ Installation Guide

Follow these steps to set up the Lodgie project on your local machine.

### 1. Prerequisites

* A web server (XAMPP, WAMP, MAMP, or a dedicated server like Nginx/Apache)
* PHP 7.4 or higher
* MySQL 5.7 or higher
* A web browser
* Composer (optional, for dependencies if you add any)

### 2. Clone the Repository

```bash
git clone [https://github.com/your-username/lodgie.git](https://github.com/your-username/lodgie.git)
cd lodgie