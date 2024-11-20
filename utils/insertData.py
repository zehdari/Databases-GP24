import pymysql
import os
import csv
from datetime import datetime

# Database connection
connection = pymysql.connect(
    host='localhost',
    user='root',
    password='mysql',
    database='GP24'
)

cursor = connection.cursor()

# Directory containing CSV files
csv_directory = '/tmp/DB_Group_Project'

# Function to reformat the date from MM/DD/YYYY to YYYY-MM-DD
def reformat_date(date_str):
    return datetime.strptime(date_str, '%m/%d/%Y').strftime('%Y-%m-%d')

# Process each CSV file in the directory
for file_name in os.listdir(csv_directory):
    if file_name.endswith('.csv'):  # Only process CSV files
        stock_symbol = file_name.split()[0].upper()  # Extract stock symbol from filename
        file_path = os.path.join(csv_directory, file_name)
        
        print(f"Processing file: {file_name} for stock: {stock_symbol}")
        
        with open(file_path, 'r') as csv_file:
            csv_reader = csv.reader(csv_file)
            next(csv_reader)  # Skip header row
            for row in csv_reader:
                try:
                    price_date = reformat_date(row[0])  # Reformat date
                    closing_price = row[1].replace(',', '')  # Remove commas if any
                    open_price = row[2].replace(',', '')  # Remove commas if any
                    high_price = row[3].replace(',', '')  # Remove commas if any
                    low_price = row[4].replace(',', '')  # Remove commas if any
                    volume = row[5].replace(',', '')  # Remove commas if any
                    change_percentage = row[6]

                    cursor.execute(
                        "INSERT INTO stocks (stock_symbol, price_date, closing_price, open_price, high_price, low_price, volume, change_percentage) "
                        "VALUES (%s, %s, %s, %s, %s, %s, %s, %s)",
                        (stock_symbol, price_date, closing_price, open_price, high_price, low_price, volume, change_percentage)
                    )
                except Exception as e:
                    print(f"Error inserting row for {stock_symbol} on {row[0]}: {e}")

# Commit changes and close connection
connection.commit()
cursor.close()
connection.close()

print("All files processed and data inserted into the database.")
