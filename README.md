# BusGo – Bus Booking Website

A full-stack bus booking website for Sudan. Users can search for trips, pick seats on a bus map, book for several passengers and print their ticket.

**Live demo:** https://busbookingmalshafie.infinityfreeapp.com/

## Features
- Search trips by origin, destination and date
- Interactive seat selection
- Booking for multiple passengers, cash or card payment option
- User accounts (register, login, my bookings)
- Printable ticket
- Arabic interface (RTL), responsive design

## Tech stack
PHP (PDO), MySQL, HTML, CSS, JavaScript, Bootstrap 5, Font Awesome

## Project structure
```
├── index.php, search.php, booking.php ...
├── admin/      admin pages
├── config/     database.php, functions.php
├── css/        stylesheets
├── js/         scripts
└── database.sql
```

## Run locally
1. Install XAMPP and copy the project into `htdocs`.
2. Create a database and import `database.sql` (collation `utf8mb4_unicode_ci`).
3. Edit `config/database.php` with your database details.
4. Open `http://localhost/<project-folder>/`.
5. Optional: update the sample trip dates so they show up:
   `UPDATE trips SET trip_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY);`

## Admin
An admin account is created by `database.sql`. Change its password after the first login.

## Author
Marwan – [GitHub](https://github.com/marwan-121)<img width="1359" height="706" alt="image" src="https://github.com/user-attachments/assets/a0d1cf77-460f-43b7-94eb-07d59d4d013f" />
