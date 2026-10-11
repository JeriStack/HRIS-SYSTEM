# Screen Wireframe Specification: Login Screen
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 4 — UI/UX Design  
**Route:** `GET /login` | `POST /login`  
**Status:** Approved for Implementation  

---

## 1. Screen Layout & Wireframe

The login view is an isolated authentication page built without the application sidebar.

```
+-----------------------------------------------------------------------------------+
|                                                                                   |
|                                [ COMPANY LOGO ]                                   |
|                 Employee HRIS & Leave Management System                           |
|                    Academic DSA Demonstration Platform                            |
|                                                                                   |
|            +---------------------------------------------------------+            |
|            |                      Sign In                            |            |
|            |                                                         |            |
|            |  [!] Alert Banner (Errors / Session Expiration Notices) |            |
|            |                                                         |            |
|            |  Username                                               |            |
|            |  +---------------------------------------------------+  |            |
|            |  | 👤 enter your username                           |  |            |
|            |  +---------------------------------------------------+  |            |
|            |                                                         |            |
|            |  Password                                               |            |
|            |  +---------------------------------------------+-----+  |            |
|            |  | 🔒 enter your password                      | 👁️  |  |            |
|            |  +---------------------------------------------+-----+  |            |
|            |                                                         |            |
|            |  [             Sign In to Your Account              ]  |            |
|            |                                                         |            |
|            |  <input type="hidden" name="_csrf_token" value="...">   |            |
|            +---------------------------------------------------------+            |
|                                                                                   |
|                     Secure Enterprise Access • PHP 8.x + MySQL                    |
+-----------------------------------------------------------------------------------+
```

---

## 2. Interactive Components & Specifications

* **Branding Header:** Displays formal project title and system badge.
* **Username Field:**
  * Label: `Username` (HTML `for="username"`).
  * Input: `<input type="text" id="username" name="username" required autofocus autocomplete="username">`.
* **Password Field:**
  * Label: `Password` (HTML `for="password"`).
  * Input: `<input type="password" id="password" name="password" required autocomplete="current-password">`.
  * Visibility Toggle: Eye icon button toggling `type="password"` and `type="text"`.
* **Hidden CSRF Protection:** `<input type="hidden" name="_csrf_token" value="<?php echo generateCsrfToken(); ?>">`.
* **Submit Action Button:** Full-width Indigo button with spinner indicator during POST dispatch.

---

## 3. UI States & Feedback Banners

1. **Default State:** Clean input fields with auto-focus set on the username field.
2. **Client/Server Form Validation Error:**
   * Red border on empty field (`#EF4444`).
   * Inline message: *"Please enter your username."*
3. **Authentication Failure State:**
   * Red alert banner at top of card:
     ```
     [!] Invalid username or password. Please verify your credentials and try again.
     ```
   * *Security Note:* Prevents account enumeration by avoiding messages like "Username does not exist" (`NFR-SEC-006`).
4. **Session Timeout State:**
   * Amber alert banner:
     ```
     [!] Your session has expired due to 30 minutes of inactivity. Please log in again.
     ```
5. **Logged Out State:**
   * Green alert banner:
     ```
     [✓] You have been successfully signed out.
     ```

---

## 4. Accessibility & Keyboard Behavior

* `Tab` cycles focus logically: `Username` -> `Password` -> `Show Password` -> `Sign In`.
* Pressing `Enter` in either text input immediately dispatches the login form.
* All inputs have explicit `<label>` tags with matching `for` attributes.
