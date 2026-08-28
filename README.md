<div align="center">
  <img src="https://img.icons8.com/color/96/000000/gas-station.png" alt="PRIMA Logo" width="80" height="80">
  <h1 align="center">PRIMA</h1>
  <p align="center">
    <strong>Pertamina Registration & Inspection Management Application</strong>
  </p>
  <p align="center">
    <img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
    <img src="https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
    <img src="https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white" alt="HTML5">
  </p>
</div>

<hr>

## Overview

PRIMA is a comprehensive digital platform engineered to manage vehicle registration, inspection checklists, and document verification for SPBU and Industrial units. Designed with a robust modular architecture, this application streamlines operational workflows, enhances data integrity, and facilitates secure digital signatures.

## Core Modules

* **Authentication & Authorization**: Secure role-based access control (RBAC) ensuring precise permission granularity for administrators, managers, and operational staff.
* **Vehicle Fleet Management**: End-to-end lifecycle management of industrial and SPBU transport vehicles, including registration and automated alerts.
* **Document Verification Engine**: A secure portal for document uploads, integrity checks, and validation.
* **Digital Signature Integration**: Cryptographically secure approval workflows utilizing RSA key generation and digital signature verification.
* **Inspection Checklists**: Digitized maintenance and compliance checklists specifically tailored for standard operating procedures.

## System Architecture

The codebase operates on a scalable, feature-driven directory structure:

* `/admin` - Administrative interfaces and audit logging mechanisms.
* `/api` - Core API endpoints managing asynchronous data operations.
* `/auth` - Authentication handlers and session management logic.
* `/config` - Environment variables, database connection strings, and system settings.
* `/docs` - Technical documentation and IT operation guides.
* `/documents` - Document processing, upload handlers, and cryptography utilities.
* `/vehicles` - Core domain logic for vehicle lifecycle management.
* `/assets` - Static resources including cascading stylesheets and client-side scripts.

## Installation Guidelines

1. Clone the repository to your local web server environment.
2. Navigate to the `/config` directory and configure your database parameters.
3. Import the initial database schema (refer to the migration scripts located in the `/scripts` directory).
4. Ensure the `/uploads` and `/logs` directories possess the appropriate write permissions.
5. Access the application via your web browser to initialize the setup sequence.

## Technical Specifications

* **Language**: PHP Native
* **Database**: MySQL / MariaDB
* **Frontend**: HTML5, CSS3, Vanilla JavaScript
* **Security**: RSA-based digital signatures, password hashing algorithms, and strict input validation.

<hr>

<div align="center">
  <p>Developed and maintained for enterprise operational excellence.</p>
</div>
