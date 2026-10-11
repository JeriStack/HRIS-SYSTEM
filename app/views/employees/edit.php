<div style="max-width: 900px; margin: 0 auto;">
    <div style="margin-bottom: 1.5rem;">
        <a href="<?= url('/hr/employees/' . $employee['id']) ?>" style="text-decoration: none; color: #4F46E5; font-size: 0.875rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem;">
            ← Back to Employee Profile
        </a>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: #1E293B; margin: 0.5rem 0 0.25rem 0;">
            Edit Employee: <?= e($employee['first_name'] . ' ' . $employee['last_name']) ?> (<?= e($employee['employee_no']) ?>)
        </h1>
        <p style="color: #64748B; margin: 0; font-size: 0.875rem;">
            Update employee demographic, contact, and organizational details below. Required fields are marked with an asterisk (*).
        </p>
    </div>

    <form method="POST" action="<?= url('/hr/employees/' . $employee['id'] . '/edit') ?>" id="employeeEditForm">
        <?= csrf_field() ?>

        <!-- Section 1: Demographic & Personal Information -->
        <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.75rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <h2 style="font-size: 1.15rem; font-weight: 600; color: #1E293B; margin: 0 0 1.25rem 0; padding-bottom: 0.75rem; border-bottom: 1px solid #F1F5F9; display: flex; align-items: center; gap: 0.5rem;">
                <span>👤</span>
                <span>Section 1: Personal & Demographic Information</span>
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem;">
                <!-- Employee Number -->
                <div class="form-group">
                    <label for="empNo" class="form-label required" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: #1E293B;">
                        Employee Number
                    </label>
                    <input type="text" 
                           id="empNo" 
                           name="employee_no" 
                           class="form-input" 
                           style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid <?= !empty($errors['employee_no']) ? '#EF4444' : '#CBD5E1' ?>; border-radius: 0.5rem; font-size: 0.875rem; box-sizing: border-box;"
                           value="<?= e($old['employee_no'] ?? $employee['employee_no']) ?>" 
                           required>
                    <?php if (!empty($errors['employee_no'])): ?>
                        <div style="color: #EF4444; font-size: 0.8125rem; margin-top: 0.25rem;"><?= e($errors['employee_no']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Email Address -->
                <div class="form-group">
                    <label for="empEmail" class="form-label required" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: #1E293B;">
                        Email Address
                    </label>
                    <input type="email" 
                           id="empEmail" 
                           name="email" 
                           class="form-input" 
                           style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid <?= !empty($errors['email']) ? '#EF4444' : '#CBD5E1' ?>; border-radius: 0.5rem; font-size: 0.875rem; box-sizing: border-box;"
                           value="<?= e($old['email'] ?? $employee['email']) ?>" 
                           required>
                    <?php if (!empty($errors['email'])): ?>
                        <div style="color: #EF4444; font-size: 0.8125rem; margin-top: 0.25rem;"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- First Name -->
                <div class="form-group">
                    <label for="firstName" class="form-label required" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: #1E293B;">
                        First Name
                    </label>
                    <input type="text" 
                           id="firstName" 
                           name="first_name" 
                           class="form-input" 
                           style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid <?= !empty($errors['first_name']) ? '#EF4444' : '#CBD5E1' ?>; border-radius: 0.5rem; font-size: 0.875rem; box-sizing: border-box;"
                           value="<?= e($old['first_name'] ?? $employee['first_name']) ?>" 
                           required>
                    <?php if (!empty($errors['first_name'])): ?>
                        <div style="color: #EF4444; font-size: 0.8125rem; margin-top: 0.25rem;"><?= e($errors['first_name']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Middle Name -->
                <div class="form-group">
                    <label for="middleName" class="form-label" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: #1E293B;">
                        Middle Name (Optional)
                    </label>
                    <input type="text" 
                           id="middleName" 
                           name="middle_name" 
                           class="form-input" 
                           style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #CBD5E1; border-radius: 0.5rem; font-size: 0.875rem; box-sizing: border-box;"
                           value="<?= e($old['middle_name'] ?? $employee['middle_name'] ?? '') ?>">
                </div>

                <!-- Last Name -->
                <div class="form-group">
                    <label for="lastName" class="form-label required" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: #1E293B;">
                        Last Name
                    </label>
                    <input type="text" 
                           id="lastName" 
                           name="last_name" 
                           class="form-input" 
                           style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid <?= !empty($errors['last_name']) ? '#EF4444' : '#CBD5E1' ?>; border-radius: 0.5rem; font-size: 0.875rem; box-sizing: border-box;"
                           value="<?= e($old['last_name'] ?? $employee['last_name']) ?>" 
                           required>
                    <?php if (!empty($errors['last_name'])): ?>
                        <div style="color: #EF4444; font-size: 0.8125rem; margin-top: 0.25rem;"><?= e($errors['last_name']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Phone -->
                <div class="form-group">
                    <label for="empPhone" class="form-label" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: #1E293B;">
                        Contact Phone (Optional)
                    </label>
                    <input type="text" 
                           id="empPhone" 
                           name="phone" 
                           class="form-input" 
                           style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #CBD5E1; border-radius: 0.5rem; font-size: 0.875rem; box-sizing: border-box;"
                           value="<?= e($old['phone'] ?? $employee['phone'] ?? '') ?>">
                </div>

                <!-- Birth Date -->
                <div class="form-group">
                    <label for="birthDate" class="form-label required" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: #1E293B;">
                        Birth Date
                    </label>
                    <input type="date" 
                           id="birthDate" 
                           name="birth_date" 
                           class="form-input" 
                           style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid <?= !empty($errors['birth_date']) ? '#EF4444' : '#CBD5E1' ?>; border-radius: 0.5rem; font-size: 0.875rem; box-sizing: border-box;"
                           value="<?= e($old['birth_date'] ?? $employee['birth_date']) ?>" 
                           required>
                    <?php if (!empty($errors['birth_date'])): ?>
                        <div style="color: #EF4444; font-size: 0.8125rem; margin-top: 0.25rem;"><?= e($errors['birth_date']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Gender -->
                <div class="form-group">
                    <label for="empGender" class="form-label required" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: #1E293B;">
                        Gender
                    </label>
                    <?php $currentGender = $old['gender'] ?? $employee['gender'] ?? ''; ?>
                    <select id="empGender" 
                            name="gender" 
                            class="form-input" 
                            style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid <?= !empty($errors['gender']) ? '#EF4444' : '#CBD5E1' ?>; border-radius: 0.5rem; font-size: 0.875rem; background: #FFFFFF; box-sizing: border-box;"
                            required>
                        <option value="">-- Select Gender --</option>
                        <option value="Male" <?= ($currentGender === 'Male') ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($currentGender === 'Female') ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= ($currentGender === 'Other') ? 'selected' : '' ?>>Other</option>
                    </select>
                    <?php if (!empty($errors['gender'])): ?>
                        <div style="color: #EF4444; font-size: 0.8125rem; margin-top: 0.25rem;"><?= e($errors['gender']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Address -->
            <div class="form-group" style="margin-top: 1.25rem;">
                <label for="empAddress" class="form-label" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: #1E293B;">
                    Residential Address (Optional)
                </label>
                <textarea id="empAddress" 
                          name="address" 
                          class="form-input" 
                          rows="2" 
                          style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #CBD5E1; border-radius: 0.5rem; font-size: 0.875rem; box-sizing: border-box;"
                          placeholder="Street, Barangay, City, Province..."><?= e($old['address'] ?? $employee['address'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Section 2: Employment & Hierarchy Information -->
        <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.75rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <h2 style="font-size: 1.15rem; font-weight: 600; color: #1E293B; margin: 0 0 1.25rem 0; padding-bottom: 0.75rem; border-bottom: 1px solid #F1F5F9; display: flex; align-items: center; gap: 0.5rem;">
                <span>🏢</span>
                <span>Section 2: Employment & Organizational Alignment</span>
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem;">
                <!-- Department Selection -->
                <div class="form-group">
                    <label for="deptSelect" class="form-label required" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: #1E293B;">
                        Department
                    </label>
                    <?php $selectedDeptId = $old['department_id'] ?? $employee['department_id']; ?>
                    <select id="deptSelect" 
                            name="department_id" 
                            class="form-input" 
                            style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid <?= !empty($errors['department_id']) ? '#EF4444' : '#CBD5E1' ?>; border-radius: 0.5rem; font-size: 0.875rem; background: #FFFFFF; box-sizing: border-box;"
                            required>
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= e($dept['id']) ?>" <?= ($selectedDeptId == $dept['id']) ? 'selected' : '' ?>>
                                <?= e($dept['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['department_id'])): ?>
                        <div style="color: #EF4444; font-size: 0.8125rem; margin-top: 0.25rem;"><?= e($errors['department_id']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Position Selection -->
                <div class="form-group">
                    <label for="posSelect" class="form-label required" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: #1E293B;">
                        Job Position
                    </label>
                    <?php $selectedPosId = $old['position_id'] ?? $employee['position_id']; ?>
                    <select id="posSelect" 
                            name="position_id" 
                            class="form-input" 
                            style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid <?= !empty($errors['position_id']) ? '#EF4444' : '#CBD5E1' ?>; border-radius: 0.5rem; font-size: 0.875rem; background: #FFFFFF; box-sizing: border-box;"
                            required>
                        <option value="">-- Select Department First --</option>
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?= e($pos['id']) ?>" 
                                    data-dept="<?= e($pos['department_id']) ?>"
                                    <?= ($selectedPosId == $pos['id']) ? 'selected' : '' ?>>
                                <?= e($pos['position_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['position_id'])): ?>
                        <div style="color: #EF4444; font-size: 0.8125rem; margin-top: 0.25rem;"><?= e($errors['position_id']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Date Hired -->
                <div class="form-group">
                    <label for="dateHired" class="form-label required" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: #1E293B;">
                        Date Hired
                    </label>
                    <input type="date" 
                           id="dateHired" 
                           name="date_hired" 
                           class="form-input" 
                           style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid <?= !empty($errors['date_hired']) ? '#EF4444' : '#CBD5E1' ?>; border-radius: 0.5rem; font-size: 0.875rem; box-sizing: border-box;"
                           value="<?= e($old['date_hired'] ?? $employee['date_hired']) ?>" 
                           required>
                    <?php if (!empty($errors['date_hired'])): ?>
                        <div style="color: #EF4444; font-size: 0.8125rem; margin-top: 0.25rem;"><?= e($errors['date_hired']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Employment Status -->
                <div class="form-group">
                    <label for="empStatus" class="form-label required" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: #1E293B;">
                        Employment Status
                    </label>
                    <?php $selectedStatus = $old['employment_status'] ?? $employee['employment_status']; ?>
                    <select id="empStatus" 
                            name="employment_status" 
                            class="form-input" 
                            style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #CBD5E1; border-radius: 0.5rem; font-size: 0.875rem; background: #FFFFFF; box-sizing: border-box;"
                            required>
                        <option value="Active" <?= ($selectedStatus === 'Active') ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($selectedStatus === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Form Submission Actions -->
        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-bottom: 3rem;">
            <a href="<?= url('/hr/employees/' . $employee['id']) ?>" class="btn btn-secondary">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem; font-weight: 600; font-size: 0.9375rem;">
                Save Changes
            </button>
        </div>
    </form>
</div>

<script>
    // Dynamic Position Filtering Based on Department Selection
    const deptSelect = document.getElementById('deptSelect');
    const posSelect = document.getElementById('posSelect');
    const allOptions = Array.from(posSelect.querySelectorAll('option[data-dept]'));
    let currentPosValue = "<?= e($selectedPosId ?? '') ?>";

    function filterPositions(keepCurrent = false) {
        const selectedDeptId = deptSelect.value;
        const targetValue = keepCurrent ? currentPosValue : posSelect.value;

        // Reset positions
        posSelect.innerHTML = '<option value="">-- Select Job Position --</option>';

        if (!selectedDeptId) {
            posSelect.innerHTML = '<option value="">-- Select Department First --</option>';
            return;
        }

        const filtered = allOptions.filter(opt => opt.getAttribute('data-dept') === selectedDeptId);

        if (filtered.length === 0) {
            posSelect.innerHTML = '<option value="">-- No Positions in this Department --</option>';
            return;
        }

        filtered.forEach(opt => {
            const clone = opt.cloneNode(true);
            if (clone.value === targetValue) {
                clone.selected = true;
            }
            posSelect.appendChild(clone);
        });
    }

    deptSelect.addEventListener('change', function() {
        filterPositions(false);
    });

    // Initial run preserving the loaded employee's position
    if (deptSelect.value) {
        filterPositions(true);
    }
</script>
