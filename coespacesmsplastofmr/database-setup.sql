-- Create the leads table with enhanced fields for tracking
CREATE TABLE leads (
    id INT(6) NOT NULL PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    name VARCHAR(255),
    phone VARCHAR(20),
    email VARCHAR(255),
    data LONGTEXT, -- Store the full JSON payload
    page_views INT DEFAULT 0,
    last_viewed DATETIME,
    call_clicks INT DEFAULT 0,
    last_call_click DATETIME,
    opted_out TINYINT(1) DEFAULT 0,
    opted_out_date DATETIME,
    sms_count INT DEFAULT 0,
    last_sms_sent DATETIME,
    has_dui TINYINT(1) DEFAULT 0,
    has_insurance TINYINT(1) DEFAULT 0, 
    is_allstate TINYINT(1) DEFAULT 0,
    notes TEXT,
    INDEX (phone),
    INDEX (opted_out),
    INDEX (created_at)
);

-- Create webhook logs table to store all incoming webhooks
CREATE TABLE webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL,
    data LONGTEXT
);

-- Create SMS history table
CREATE TABLE sms_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT(6),
    sent_at DATETIME NOT NULL,
    message_text TEXT,
    twilio_sid VARCHAR(255),
    status VARCHAR(50),
    FOREIGN KEY (lead_id) REFERENCES leads(id),
    INDEX (lead_id),
    INDEX (sent_at)
);

-- Create scheduled SMS table for follow-ups
CREATE TABLE scheduled_sms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT(6),
    schedule_time DATETIME NOT NULL,
    message_text TEXT,
    is_sent TINYINT(1) DEFAULT 0,
    sent_at DATETIME NULL,
    sms_type VARCHAR(50),
    FOREIGN KEY (lead_id) REFERENCES leads(id),
    INDEX (schedule_time),
    INDEX (is_sent)
);