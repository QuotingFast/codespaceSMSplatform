CREATE TABLE leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255),
  phone VARCHAR(25),
  email VARCHAR(255),
  data JSON,
  created_at DATETIME
);