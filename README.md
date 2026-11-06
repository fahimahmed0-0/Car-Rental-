# Car Rental System

## Project Overview
This is a car rental management system built with PHP that allows users to browse, book, and manage car rentals. The system includes both user and admin interfaces with features like vehicle management, booking management, and user management.

## Security Setup for GitHub

This repository has been prepared for public sharing on GitHub by removing sensitive information. Follow these steps to set up the project securely:

### Environment Variables

1. Create a `.env` file in the project root based on the provided `.env.example` template:
   ```
   cp .env.example .env
   ```

2. Update the `.env` file with your actual database credentials and email settings.

### Database Setup

1. Create a new database for the application.
2. Import the database structure from `database/carrental.template.sql`:
   ```
   mysql -u your_username -p your_database_name < database/carrental.template.sql
   ```
3. Update the admin password in the database with a secure hashed password.
4. Add the payment tables from the command line (this script cannot run from a browser):
   ```
   php database/update_database.php
   ```

### Email Configuration

The application uses PHPMailer for sending emails. Configure your SMTP settings in the `.env` file:

```
SMTP_HOST=your_smtp_server
SMTP_USER=your_email@example.com
SMTP_PASS=your_email_password
SMTP_SECURE=tls
SMTP_PORT=587

EMAIL_FROM=noreply@example.com
EMAIL_FROM_NAME="Car Rental System"
EMAIL_TO=admin@example.com
EMAIL_TO_NAME=Administrator
```

## Development Setup

1. Clone the repository to your local environment.
2. Set up your web server (Apache/Nginx) to point to the project directory.
3. Configure your PHP environment (PHP 7.2+ recommended).
4. Follow the security setup steps above.

## Production Deployment

For production deployment:

1. Ensure all sensitive information is stored in environment variables.
2. Update the `.env` file with production credentials.
3. Set appropriate file permissions.
4. Enable HTTPS for secure communication.

## Security Considerations

- Never commit `.env` files or any files containing sensitive information.
- Regularly update dependencies and check for security vulnerabilities.
- Use prepared statements for all database queries to prevent SQL injection.
- Implement proper input validation and sanitization.
- Use HTTPS in production environments.

## License

[Add your license information here]