#!/bin/bash

# Define the source directory containing the CSV files
SOURCE_DIR="data"
DEST_DIR="/tmp/data"

# Define the SQL script file path
SQL_SCRIPT="utils/CreateDB.sql"

# Array of CSV files to copy
CSV_FILES=("META Historical Data.csv" "AAPL Historical Data.csv" "GOOGL Historical Data.csv" "AMZN Historical Data.csv")

# Create the destination directory if it doesn't exist
if [ ! -d "$DEST_DIR" ]; then
    echo "Creating directory $DEST_DIR..."
    mkdir -p "$DEST_DIR"
fi

# Copy the CSV files
echo "Copying CSV files to $DEST_DIR..."
for file in "${CSV_FILES[@]}"; do
    src_file="$SOURCE_DIR/$file"
    dest_file="$DEST_DIR/$file"

    if [ -f "$src_file" ]; then
        cp "$src_file" "$dest_file"
        echo "Copied $src_file to $dest_file."
    else
        echo "Warning: $src_file does not exist and was skipped."
    fi
done

# Run the SQL script to create the database
if [ -f "$SQL_SCRIPT" ]; then
    echo "Running SQL script $SQL_SCRIPT..."
    echo "Enter password for root:"
    mysql -u root -p < "$SQL_SCRIPT"
    if [ $? -eq 0 ]; then
        echo "Database setup complete."
    else
        echo "Error: Failed to run the SQL script."
        exit 1
    fi
else
    echo "Error: SQL script $SQL_SCRIPT not found."
    exit 1
fi