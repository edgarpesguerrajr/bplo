# Franchise Document Audit History Setup Guide

This guide explains how to enable the new audit history feature for the BPLO franchise monitoring system.

## What's New?

The audit history feature preserves all historical data when documents are updated:
- **Original data preserved**: When you edit a franchise document, the old values are saved to history
- **Timeline view**: In view_document.php, you can click between different versions to see how data evolved
- **Full transparency**: Track all changes with timestamps

### Example:
- **Created**: TODA No. = `PINTODA 001`
- **Updated after 6 months**: TODA No. = `PINTODA 020`
- **View document**: Can see both versions with dates and times

## Setup Instructions

### Step 1: Create the History Table

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Select your database (bplo database)
3. Click "SQL" tab
4. Copy and paste the SQL from: `c:\xampp\htdocs\bplo\database\create_document_history_table.sql`
5. Click "Go" to execute

Or run via MySQL command line:
```bash
mysql -u root -p your_database_name < c:\xampp\htdocs\bplo\database\create_document_history_table.sql
```

### Step 2: Verify Installation

After creating the table:
1. Go to a franchise document in your system
2. Edit any field (e.g., TODA No.)
3. Click Save
4. Go to View Document
5. You should now see an "Update History" section showing versions

### Step 3: View Historical Data

**In View Document:**
- **Current**: Shows the latest data (default view)
- **v1, v2, etc.**: Click to see data from that version with the timestamp
- Each version shows exactly what was saved at that specific update

### For Existing Data

The first time you edit an existing document, it will:
1. Save the current state as version 1 to history
2. Apply your new changes to the current document

This way, documents edited before this feature was enabled will still have their pre-update state saved.

## How It Works

**After editing and saving:**
```
1. Current document data → saved to document_history table (version N)
2. New values → saved to documents table (current)
3. document_history stores old, documents stores new
```

**When viewing:**
```
- Click "Current" → see latest data from documents table
- Click "v1" → see original/historical data from document_history
- Click "v2" → see second version (first update)
- etc.
```

## Database Structure

### document_history Table
- Stores all historical snapshots of franchise data
- Same fields as documents table plus:
  - `version`: Sequential version number
  - `changed_at`: Timestamp of when this was the current state
  - `document_id`: Link to original document

## Support

If you encounter issues:
1. Verify document_history table exists: `SHOW TABLES;` in phpmyadmin
2. Check that edit_document.php has been updated (should have history insert code)
3. Ensure all fields in history table match documents table fields

