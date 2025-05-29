# Simple Database Shell Interface

A simple and user-friendly web interface for managing MySQL databases. This tool allows you to:
- Connect to MySQL databases
- View all available databases
- List all tables within each database
- Manage database connections securely

## Features

- Clean and modern user interface
- Secure database connections using PDO
- Session management for persistent connections
- Responsive design for all devices
- Easy-to-use navigation
- Proper error handling and user feedback

## Requirements

- PHP 7.0 or higher
- MySQL server
- Web server (Apache, Nginx, etc.)
- PDO PHP extension

## Installation

1. Clone this repository:
```bash
git clone https://github.com/SmokingAirLinesTG/simpleshell.git
```

2. Place the files in your web server directory

3. Access the interface through your web browser:
```
http://your-server/shell.php
```

## Usage

1. Enter your database credentials:
   - Host (default: localhost)
   - Username
   - Password

2. Click "Connect" to establish the database connection

3. Browse your databases and tables

4. Use the "Disconnect" button when finished

## Security

- Uses PDO for secure database connections
- Implements session management
- Properly escapes output
- Password field is properly masked

## License

MIT License 