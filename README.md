# Project Setup Instructions

## ALTERNATIVE

To set up the DB without the .bat script, the SQL setup file for relative folders (i.e. "../data/AAPL Historical Data.csv") is in utils/CreateDBWindowsNobat.sql
Run the sql script, then proceed to step 2.

## 1. Run the Database Setup Script

To set up the database, execute the following command:

### For MacOS/Linux

```bash
bash setup.bash
```

### For Windows

```bash
setup.bat
```

## 2. Start the PHP Server

Start the PHP development server with the following command:

```bash
php -S localhost:8000 -t public
```

## 3. Access the Application

Open your browser and navigate to:

[http://localhost:8000/index.php](http://localhost:8000/index.php)

## Member Contributions

**Cameron Tucker**: Developed the application, including the PHP, JavaScript, HTML, and CSS. Wrote the majority of the SQL queries and managed the overall functionality. Created the setup scripts and validated the application with testing.

**JiaCheng Xue**:  Developed the part of the application PHP; testing installment and functionality of application on Windows environment.

**Saleh Zakzok**: Tested the application once completed by testing edge cases and attempting to break the application.
