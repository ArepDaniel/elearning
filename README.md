Here's the complete `README.md` file in proper Markdown format that you can copy and use directly:

```markdown
# USAS E-Learning System

A comprehensive web-based e-learning management platform designed to support students, lecturers, and administrators in managing subjects, users, and learning materials efficiently.

## Table of Contents
- [Features](#features)
- [Technologies Used](#technologies-used)
- [Installation](#installation)
- [Database Schema](#database-schema)
- [Usage](#usage)
- [File Upload Formats](#file-upload-formats)
- [User Roles](#user-roles)
- [Contributing](#contributing)
- [License](#license)

## Features

### Admin Portal
- User management:
  - Add users manually (matrix number, IC number, username, role)
  - Bulk upload users via Excel (.xlsx) files
  - View, search, filter (by role), and paginate user list
  - Edit/delete users
- Subject management:
  - View all subjects with lecturer information
  - Delete subjects (cascades to related questions and results)

### Lecturer Portal
- Subject management:
  - Create new subjects (code, name, year, semester)
  - View/delete their own subjects
- Question management:
  - Add questions manually (with bilingual English/Malay support)
  - Upload questions via DOCX or PDF files (auto-parsed)
  - View questions per subject
- Student performance tracking:
  - View student attempts and scores
  - Filter by student matrix number
  - Overall and per-subject statistics

### Student Portal
- Subject access:
  - View available subjects with filtering
  - Answer questions in interactive format
- Performance tracking:
  - View answer history with scores
  - Filter by subject
  - Overall statistics (attempts, correct answers, percentage)

## Technologies Used
- **Frontend**: HTML5, CSS3, Bootstrap 5, JavaScript
- **Backend**: PHP
- **Database**: MySQL
- **Libraries/Tools**:
  - `PHPWord` for DOCX parsing
  - `pdftotext` for PDF question extraction
  - `ZipArchive` for Excel file processing
  - `SimpleXML` for XML parsing

## Installation

1. **Prerequisites**
   - Web server (Apache/Nginx)
   - PHP 8.0+ with extensions: mbstring, zip, xml, gd
   - MySQL 5.7+
   - pdftotext utility (for PDF processing)

2. **Setup**
   ```bash
   git clone https://github.com/ArepDaniel/elearning.git
   cd usas-elearning
   ```

3. **Database Configuration**
   - Import the provided `db_usas_elearning.sql` file
   - Update `config.php` with your database credentials:
     ```php
     $host = 'localhost';
     $user = 'your_username';
     $pass = 'your_password';
     $dbname = 'db_usas_elearning';
     ```

4. **File Uploads**
   - Create an `uploads` directory with write permissions
   - For PDF processing, ensure `pdftotext` is installed and path is configured in `lecturer_homepage.php`

5. **Access**
   - Navigate to `login.php` in your web browser
   - Use default credentials from the database dump to log in

## Database Schema

### `user` Table
| Column         | Type         | Description                          |
|----------------|--------------|--------------------------------------|
| matrix_number  | VARCHAR(20)  | Primary key (student/lecturer ID)    |
| ic_number      | VARCHAR(20)  | National identification number       |
| username       | VARCHAR(50)  | Display name                         |
| role           | ENUM         | 'student', 'lecturer', or 'admin'    |
| created_at     | TIMESTAMP    | Account creation timestamp           |

### `subjects` Table
| Column         | Type         | Description                          |
|----------------|--------------|--------------------------------------|
| id             | INT          | Auto-increment primary key           |
| subject_code   | VARCHAR(20)  | Unique subject identifier            |
| subject_name   | VARCHAR(100) | Full subject name                    |
| year           | INT          | Academic year                        |
| semester       | VARCHAR(50)  | Academic semester                    |
| matrix_number  | VARCHAR(20)  | Lecturer who created the subject     |
| created_at     | TIMESTAMP    | Subject creation timestamp           |

### `questions` Table
| Column         | Type         | Description                          |
|----------------|--------------|--------------------------------------|
| id             | INT          | Auto-increment primary key           |
| subject_id     | INT          | Foreign key to subjects table        |
| question_text  | TEXT         | Question content (bilingual)         |
| option_a       | VARCHAR(255) | First answer option                  |
| option_b       | VARCHAR(255) | Second answer option                 |
| option_c       | VARCHAR(255) | Third answer option                  |
| option_d       | VARCHAR(255) | Fourth answer option                 |
| correct_answer | ENUM         | 'A', 'B', 'C', or 'D'                |
| created_at     | TIMESTAMP    | Question creation timestamp          |

### `question_documents` Table
| Column         | Type         | Description                          |
|----------------|--------------|--------------------------------------|
| id             | INT          | Auto-increment primary key           |
| subject_id     | INT          | Foreign key to subjects table        |
| filename       | VARCHAR(255) | Original uploaded file name          |
| filepath       | VARCHAR(255) | Server path to stored file           |
| uploaded_at    | TIMESTAMP    | Upload timestamp                     |

### `student_results` Table
| Column           | Type         | Description                          |
|------------------|--------------|--------------------------------------|
| id               | INT          | Auto-increment primary key           |
| matrix_number    | VARCHAR(20)  | Foreign key to user table            |
| subject_id       | INT          | Foreign key to subjects table        |
| score_percentage | DECIMAL(5,2) | Percentage of correct answers        |
| correct_answers  | INT          | Number of correct answers            |
| total_questions  | INT          | Total questions attempted            |
| attempt_date     | TIMESTAMP    | When the attempt was made            |

## Usage

### For Admins:
1. Log in at `login.php` with admin credentials
2. Access the admin portal at `admin_homepage.php`
3. Manage users through the interface:
   - Add individual users via form
   - Bulk upload via Excel (columns: matrix_number, ic_number, username, role)
   - View/edit/delete existing users
4. Manage subjects:
   - View all subjects with filtering
   - Delete subjects (cascades to related data)

### For Lecturers:
1. Log in with lecturer credentials
2. Access the lecturer portal at `lecturer_homepage.php`
3. Create and manage subjects:
   - Add new subjects with code, name, year, and semester
   - View/delete your subjects
4. Add questions:
   - Manually (with bilingual English/Malay support)
   - Via DOCX or PDF upload (auto-parsed)
5. Track student performance:
   - View overall and per-subject statistics
   - Filter by student matrix number

### For Students:
1. Log in with student credentials
2. Access the student portal at `homepage.php`
3. View available subjects with filtering options
4. Answer questions through the interactive interface
5. Review performance:
   - View answer history
   - See overall statistics
   - Filter by subject

## File Upload Formats

### Excel User Upload (.xlsx)
Required columns (first row as headers):
1. matrix_number
2. ic_number
3. username
4. role (must be 'student', 'lecturer', or 'admin')

Example:
| matrix_number | ic_number   | username    | role     |
|---------------|-------------|-------------|----------|
| D22114691     | 040727070605| Arif Daniel | student  |

### Question Document Upload (.docx or .pdf)
Supported formats:

**Bilingual Format:**
```
Q1. What does "CPU" stand for?
Apakah maksud "CPU"?
A) Central Processing Unit / Unit Pemprosesan Pusat
B) Computer Power Unit / Unit Kuasa Komputer
C) Central Printing Unit / Unit Percetakan Pusat
D) Core Processing Utility / Utiliti Pemprosesan Teras
Correct answer: A / Jawapan: A
```

**Single Language Format:**
```
Q2. Which device is used to connect a computer to a network?
A) Printer
B) Scanner
C) Modem
D) Monitor
Correct answer: C
```

## User Roles

- **Admin**: 
  - Full system access
  - Manage all users
  - View/delete all subjects
- **Lecturer**: 
  - Create/manage their own subjects
  - Add/upload questions
  - View student performance for their subjects
- **Student**: 
  - View available subjects
  - Answer questions
  - View their own performance history

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/your-feature`)
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Push to the branch (`git push origin feature/your-feature`)
5. Create a new Pull Request

Please ensure your code follows the existing style and includes appropriate documentation.

