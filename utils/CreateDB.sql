-- Create the GP24 database if it doesn't exist
CREATE DATABASE IF NOT EXISTS GP24;

-- Use the GP24 database
USE GP24;

-- Create the stocks table
CREATE TABLE IF NOT EXISTS stocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stock_symbol VARCHAR(10) NOT NULL,     
    price_date DATE NOT NULL,              
    closing_price DECIMAL(10, 2) NOT NULL, 
    open_price DECIMAL(10, 2),             
    high_price DECIMAL(10, 2),             
    low_price DECIMAL(10, 2),              
    volume VARCHAR(20),                    
    change_percentage VARCHAR(10)          
);

-- Create the users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,  
    username VARCHAR(50) NOT NULL UNIQUE,    
    password_hash VARCHAR(255) NOT NULL,      
);

-- Create the transactions table
CREATE TABLE IF NOT EXISTS transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,                     
    total_investment DECIMAL(10, 2) NOT NULL,  
    fees DECIMAL(10, 2) NOT NULL,              
    purchase_date DATE NOT NULL,              
    sell_date DATE NOT NULL,                   
    gain_loss DECIMAL(10, 2),                  
    tax DECIMAL(10, 2),                       
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Create the transaction_allocations table
CREATE TABLE IF NOT EXISTS transaction_allocations (
    allocation_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    stock_ticker VARCHAR(10) NOT NULL,
    allocation_amount DECIMAL(10, 2) NOT NULL,
    gain_loss DECIMAL(10, 2) NOT NULL DEFAULT 0,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE CASCADE
);

-- Create a temporary table to load the CSV data
CREATE TEMPORARY TABLE temp_stock_data (
    price_date VARCHAR(20),
    closing_price VARCHAR(20),
    open_price VARCHAR(20),
    high_price VARCHAR(20),
    low_price VARCHAR(20),
    volume VARCHAR(20),
    change_percentage VARCHAR(20)
);

-- Load data for stock symbol GOOGL
LOAD DATA INFILE '/tmp/DB_Group_Project/GOOGL Historical Data.csv'
INTO TABLE temp_stock_data
FIELDS TERMINATED BY ',' 
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS;

-- Insert data into the stocks table for GOOGL
INSERT INTO stocks (stock_symbol, price_date, closing_price, open_price, high_price, low_price, volume, change_percentage)
SELECT
    'GOOGL' AS stock_symbol,
    STR_TO_DATE(price_date, '%m/%d/%Y') AS price_date,
    REPLACE(closing_price, ',', '') AS closing_price,
    REPLACE(open_price, ',', '') AS open_price,
    REPLACE(high_price, ',', '') AS high_price,
    REPLACE(low_price, ',', '') AS low_price,
    volume,
    change_percentage
FROM temp_stock_data;

-- Clear the temporary table
TRUNCATE TABLE temp_stock_data;

-- Load data for stock symbol META
LOAD DATA INFILE '/tmp/DB_Group_Project/META Historical Data.csv'
INTO TABLE temp_stock_data
FIELDS TERMINATED BY ',' 
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS;

-- Insert data into the stocks table for META
INSERT INTO stocks (stock_symbol, price_date, closing_price, open_price, high_price, low_price, volume, change_percentage)
SELECT
    'META' AS stock_symbol,
    STR_TO_DATE(price_date, '%m/%d/%Y') AS price_date,
    REPLACE(closing_price, ',', '') AS closing_price,
    REPLACE(open_price, ',', '') AS open_price,
    REPLACE(high_price, ',', '') AS high_price,
    REPLACE(low_price, ',', '') AS low_price,
    volume,
    change_percentage
FROM temp_stock_data;

-- Clear the temporary table
TRUNCATE TABLE temp_stock_data;

-- Load data for stock symbol AAPL
LOAD DATA INFILE '/tmp/DB_Group_Project/AAPL Historical Data.csv'
INTO TABLE temp_stock_data
FIELDS TERMINATED BY ',' 
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS;

-- Insert data into the stocks table for AAPL
INSERT INTO stocks (stock_symbol, price_date, closing_price, open_price, high_price, low_price, volume, change_percentage)
SELECT
    'AAPL' AS stock_symbol,
    STR_TO_DATE(price_date, '%m/%d/%Y') AS price_date,
    REPLACE(closing_price, ',', '') AS closing_price,
    REPLACE(open_price, ',', '') AS open_price,
    REPLACE(high_price, ',', '') AS high_price,
    REPLACE(low_price, ',', '') AS low_price,
    volume,
    change_percentage
FROM temp_stock_data;

-- Clear the temporary table
TRUNCATE TABLE temp_stock_data;

-- Load data for stock symbol AMZN
LOAD DATA INFILE '/tmp/DB_Group_Project/AMZN Historical Data.csv'
INTO TABLE temp_stock_data
FIELDS TERMINATED BY ',' 
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS;

-- Insert data into the stocks table for AMZN
INSERT INTO stocks (stock_symbol, price_date, closing_price, open_price, high_price, low_price, volume, change_percentage)
SELECT
    'AMZN' AS stock_symbol,
    STR_TO_DATE(price_date, '%m/%d/%Y') AS price_date,
    REPLACE(closing_price, ',', '') AS closing_price,
    REPLACE(open_price, ',', '') AS open_price,
    REPLACE(high_price, ',', '') AS high_price,
    REPLACE(low_price, ',', '') AS low_price,
    volume,
    change_percentage
FROM temp_stock_data;

-- Drop the temporary table
DROP TEMPORARY TABLE temp_stock_data;
