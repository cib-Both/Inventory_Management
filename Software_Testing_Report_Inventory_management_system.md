# SOFTWARE TESTING REPORT

## Inventory Management System | Laravel 11 + Filament

## 1. Project Information

Project Title: Inventory Management System  
Tech Stack: Laravel 11, Filament Admin Panel, Livewire, Tailwind CSS, MySQL  
Course / Subject: Software Testing  
Group 4: Chem Indraboth, Nat Sinoeun, Khun Kimheng, Thy Sovannmakara, Nhun Pisal  
Instructor: Mr. Heng Chanarin  
Date: May 30, 2026  

## 2. System Overview

This project is a fully-featured Inventory Management System built with Laravel 11 and the Filament admin panel framework. It provides a responsive, modern UI backed by Laravel's Eloquent ORM and secure authentication system.

Purpose: Manage products, categories, stock in/out transactions, user authentication, roles, and permissions.

Target Users: Business admins, inventory managers, warehouse staff.

Main Features:

- Product management
- Category management with product associations
- Supplier management
- Loan inventory management
- Stock In / Stock Out transaction recording
- Dashboard with inventory overview
- Admin login and role/permission management

## 3. Testing Objectives

- Validate CRUD operations for Products, Categories, Suppliers, Purchases, Inventory, Loans, and Users
- Detect bugs and logical errors in inventory and stock management modules
- Verify authentication and authorization controls
- Ensure model relationships work correctly
- Confirm inventory status integrity when loans are active or returned
- Confirm user role assignment works correctly

## 4. Testing Scope

In Scope:

- Authentication module
- Admin access
- Category model
- Supplier model
- Product model
- Purchase model
- PurchaseProduct model
- Inventory model
- Loan model
- User model
- Role assignment
- Soft delete and restore behavior

Out of Scope:

- Database migration performance under high load
- Third-party package internals
- Email and notification integrations
- Full browser automation testing

## 5. Testing Strategy / Approach

Testing Types:

- Unit Testing
- Feature / Integration Testing

Methods:

- Black-box testing for application behavior
- White-box testing for Laravel model logic and relationships

Framework:

- PHPUnit / Laravel Artisan test runner

Command Used:

```bash
php artisan test tests/Feature/CoreFlow.php tests/Unit/UserModel.php
```

## 6. Test Environment

Hardware: Laptop  
Operating System: macOS  
PHP Version: PHP 8.4.12  
Framework: Laravel 11  
Database: SQLite in-memory database for automated testing  
Testing Tools: PHPUnit, Laravel Artisan test runner  

## 7. Test Cases

| Test Case ID | Test Scenario | Test Steps | Expected Result | Status | Remarks |
|---|---|---|---|---|---|
| TC01 | Admin Can Access Home | Create admin role -> create admin user -> assign role -> GET `/` | HTTP 200 response | Pass | Vite disabled during test |
| TC02 | Admin Has Admin Role | Create admin user -> check `hasRole('admin')` | Returns true | Pass | - |
| TC03 | Category Can Be Created | `Category::create()` -> `assertDatabaseHas` | Record exists in categories table | Pass | - |
| TC04 | Supplier Can Be Created | `Supplier::create()` -> `assertDatabaseHas` | Record exists in suppliers table | Pass | - |
| TC05 | Product Can Be Created | Create category and supplier -> create product | Product exists in database | Pass | - |
| TC06 | Product Belongs to Category | Create product -> check category relationship | Product category matches | Pass | - |
| TC07 | Product Belongs to Supplier | Create product -> check supplier relationship | Product supplier matches | Pass | - |
| TC08 | Category Can Have Products | Create two products under same category | Category product count = 2 | Pass | - |
| TC09 | Multiple Products Share Category | Create two products with same category ID | Both products share same category | Pass | - |
| TC10 | Inventory Created from Purchase | Create purchase -> create inventory record | Inventory exists with qty 10 and status available | Pass | - |
| TC11 | Purchase Can Be Created | Create purchase record | Purchase exists with pending status | Pass | - |
| TC12 | Purchase Product Can Be Created | Create purchase product record | PurchaseProduct exists with serial number | Pass | - |
| TC13 | Loan Marks Inventory as Loaned | Create active loan for inventory | Inventory status becomes loaned | Pass | - |
| TC14 | Inventory Belongs to Purchase | Create inventory with purchase ID | Inventory purchase ID matches purchase | Pass | - |
| TC15 | Inventory Quantity Can Be Summed | Create two inventory records qty 4 and 6 | Sum equals 10 | Pass | - |
| TC16 | Loan Belongs to Inventory | Create loan for inventory | Loan inventory ID matches inventory | Pass | - |
| TC17 | Loan Belongs to Department | Create loan for department | Loan department ID matches department | Pass | - |
| TC18 | Loan Activation Sets Inventory Loaned | Create active loan | Inventory status becomes loaned | Pass | - |
| TC19 | Returned Loan Marks Inventory Available | Update loan status to returned | Inventory status becomes available | Pass | - |
| TC20 | Multiple Inventory Items for Same Product | Create two inventory records for same product | Count = 2 | Pass | - |
| TC21 | Multiple Loans for Different Inventory Items | Create two inventory records and loan both | Loan count = 2 | Pass | - |
| TC22 | User Can Be Created | `User::create()` -> `assertDatabaseHas` | User exists in database | Pass | - |
| TC23 | User Password Is Hashed | Create user -> compare password | Stored password is not plain text | Pass | - |
| TC24 | User Can Have Roles | Create role `teacher` -> create user -> assign role | User has teacher role | Pass | Role is created before assignment |
| TC25 | User Can Have Multiple Roles | Create roles `teacher` and `user` -> assign both | User has both roles | Pass | Roles are created before assignment |
| TC26 | User Email Must Be Unique | Create duplicate email user | QueryException thrown | Pass | - |
| TC27 | User Can Be Soft Deleted | Create user -> delete user | User has deleted_at value | Pass | - |
| TC28 | User Can Be Restored After Soft Delete | Delete user -> restore user | User record is restored | Pass | - |
| TC29 | User Hidden Attributes | Convert user to array | Password and remember_token are hidden | Pass | - |

## 8. Defect Report

| Defect ID | Description | Severity | Steps to Reproduce | Status |
|---|---|---|---|---|
| D01 | User role assignment originally failed when the `teacher` role did not exist before `assignRole()`. The test now creates the role first. | High | Run `UserModel` test -> create role `teacher` -> create user -> assign role -> check `hasRole('teacher')` | Fixed |
| D02 | Multiple role assignment originally failed when required roles did not exist before `assignRole()`. The test now creates both roles first. | High | Run `UserModel` test -> create roles `teacher` and `user` -> create user -> assign both roles -> check both roles | Fixed |

## 9. Test Results Summary

| Total Test Cases | Passed | Failed | Pass Rate |
|---|---|---|---|
| 29 | 29 | 0 | 100% |

Test execution result:

```text
Tests: 29 passed
Assertions: 36
Status: Passed
```

## 10. Challenges & Limitations

- Filament panel components rely on Livewire, making automated browser-level testing require additional setup such as Laravel Dusk.
- Frontend Vite assets are not required for model and feature logic testing, so Vite was disabled during the home route test.
- Database seeding must be handled carefully to ensure consistent test data.
- Current tests focus on core models and relationships, not full UI browser workflows.
- Validation tests for required fields can be added in future testing.

## 11. Conclusion & Recommendations

The Inventory Management System core test suite now passes all documented test cases. Authentication access, product/category/supplier setup, purchase and inventory records, loan status updates, and user model behavior all pass in the automated PHPUnit run.

Priority Fixes Completed:

- D01 fixed: Role record is created before assigning `teacher`.
- D02 fixed: Role records are created before assigning multiple roles.
- PHPUnit test environment configured with SQLite in-memory database.
- Vite disabled during feature testing to avoid frontend build dependency.

General Recommendations:

- Add validation tests for required Product, Supplier, Purchase, Inventory, and Loan fields.
- Add feature tests for Filament CRUD pages.
- Add browser tests for full admin panel workflows.
- Add negative tests for invalid inventory quantity and invalid loan status.
