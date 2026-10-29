<div class="content-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: #1E293B; margin: 0 0 0.25rem 0;">Employees Directory</h1>
        <p style="color: #64748B; margin: 0; font-size: 0.875rem;">Centralized workforce registry, demographic profiles, and status lifecycle management.</p>
    </div>
    <div>
        <a href="<?= url('/hr/employees/create') ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
            <span>➕</span>
            <span>Add New Employee</span>
        </a>
    </div>
</div>

<!-- Workforce Metrics Bar -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.5rem; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <div style="font-size: 0.75rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Total Workforce</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #1E293B;"><?= e((string)($counts['total'] ?? 0)) ?></div>
        </div>
        <span style="font-size: 1.5rem;">👥</span>
    </div>
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.5rem; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <div style="font-size: 0.75rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Active Employees</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #059669;"><?= e((string)($counts['active'] ?? 0)) ?></div>
        </div>
        <span style="font-size: 1.5rem;">🟢</span>
    </div>
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.5rem; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <div style="font-size: 0.75rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Inactive / On Leave</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #DC2626;"><?= e((string)($counts['inactive'] ?? 0)) ?></div>
        </div>
        <span style="font-size: 1.5rem;">🔴</span>
    </div>
</div>

<!-- Search & Filtering Hub -->
<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1rem 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <form method="GET" action="<?= url('/hr/employees') ?>" class="search-bar-wrap">
        <div style="flex: 1; min-width: 240px; position: relative;">
            <input type="text" 
                   name="q" 
                   class="form-input" 
                   placeholder="Search by Employee ID, Name, or Email..." 
                   value="<?= e($searchQuery ?? '') ?>"
                   style="width: 100%; padding: 0.55rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem; box-sizing: border-box;">
        </div>

        <div style="min-width: 180px;">
            <select name="dept" class="form-input" style="width: 100%; padding: 0.55rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem; background: #FFFFFF; box-sizing: border-box;">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= e($d['id']) ?>" <?= (!empty($selectedDept) && $selectedDept == $d['id']) ? 'selected' : '' ?>>
                        <?= e($d['department_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="min-width: 150px;">
            <select name="status" class="form-input" style="width: 100%; padding: 0.55rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem; background: #FFFFFF; box-sizing: border-box;">
                <option value="">All Statuses</option>
                <option value="Active" <?= (!empty($selectedStatus) && $selectedStatus === 'Active') ? 'selected' : '' ?>>Active Only</option>
                <option value="Inactive" <?= (!empty($selectedStatus) && $selectedStatus === 'Inactive') ? 'selected' : '' ?>>Inactive Only</option>
            </select>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.55rem 1rem;">
                🔍 Search
            </button>
            <?php if (!empty($searchQuery) || !empty($selectedDept) || !empty($selectedStatus) || !empty($sortField)): ?>
                <a href="<?= url('/hr/employees') ?>" class="btn btn-secondary" style="padding: 0.55rem 0.85rem;" title="Reset Filters">
                    ✕ Reset
                </a>
            <?php endif; ?>
        </div>

        <!-- Phase 10: Custom DSA Sorting Controls -->
        <div style="flex-basis: 100%; height: 0;"></div>
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; width: 100%; padding-top: 0.75rem; margin-top: 0.25rem; border-top: 1px dashed #E2E8F0;">
            <span style="font-size: 0.75rem; font-weight: 600; color: #64748B; text-transform: uppercase;">Sort (custom algorithm):</span>

            <select name="sort" class="form-input" aria-label="Sort field" style="min-width: 160px; padding: 0.45rem 0.7rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.8125rem; background: #FFFFFF;">
                <option value="">Default (Name)</option>
                <?php foreach (($sortFields ?? []) as $key => $col): ?>
                    <option value="<?= e($key) ?>" <?= (($sortField ?? '') === $key) ? 'selected' : '' ?>>
                        <?= e(ucwords(str_replace('_', ' ', $key))) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="dir" class="form-input" aria-label="Sort direction" style="min-width: 120px; padding: 0.45rem 0.7rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.8125rem; background: #FFFFFF;">
                <option value="asc" <?= (($sortDir ?? 'asc') === 'asc') ? 'selected' : '' ?>>Ascending</option>
                <option value="desc" <?= (($sortDir ?? 'asc') === 'desc') ? 'selected' : '' ?>>Descending</option>
            </select>

            <select name="algo" class="form-input" aria-label="Sorting algorithm" style="min-width: 150px; padding: 0.45rem 0.7rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.8125rem; background: #FFFFFF;">
                <?php foreach (($sortAlgorithms ?? []) as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= (($sortAlgo ?? 'quick') === $key) ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 0.45rem 0.85rem;">Apply Sort</button>

            <?php if (!empty($dsaMeta)): ?>
                <span class="badge badge-info" title="Executed by the custom DSA engine over the authorized dataset" style="margin-left: auto;">
                    <?= e($dsaMeta['algorithm_name']) ?> &middot;
                    <?= e(ucwords(str_replace('_', ' ', $dsaMeta['field']))) ?>
                    (<?= e($dsaMeta['direction'] === 'desc' ? '↓' : '↑') ?>) &middot;
                    <?= e((string)$dsaMeta['count']) ?> rows &middot;
                    <?= e((string)$dsaMeta['elapsed_ms']) ?> ms
                    <?= !empty($dsaMeta['capped']) ? '&middot; capped at ' . e((string)$dsaMeta['cap']) : '' ?>
                </span>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Employees Directory Table -->
<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden;">
    <div class="table-responsive">
        <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
            <thead>
                <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; color: #475569; text-align: left;">
                    <th style="padding: 0.875rem 1.25rem; font-weight: 600;">Employee No</th>
                    <th style="padding: 0.875rem 1.25rem; font-weight: 600;">Full Name</th>
                    <th style="padding: 0.875rem 1.25rem; font-weight: 600;">Email & Contact</th>
                    <th style="padding: 0.875rem 1.25rem; font-weight: 600;">Department & Role</th>
                    <th style="padding: 0.875rem 1.25rem; font-weight: 600; text-align: center;">Status</th>
                    <th style="padding: 0.875rem 1.25rem; font-weight: 600;">Date Hired</th>
                    <th style="padding: 0.875rem 1.25rem; font-weight: 600; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($employees)): ?>
                    <?php foreach ($employees as $emp): 
                        $initials = strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1));
                    ?>
                        <tr style="border-bottom: 1px solid #F1F5F9; transition: background 0.15s ease;">
                            <td style="padding: 0.875rem 1.25rem;">
                                <code style="font-weight: 600; background: #EEF2FF; color: #4F46E5; padding: 0.2rem 0.45rem; border-radius: 0.25rem;"><?= e($emp['employee_no']) ?></code>
                            </td>
                            <td style="padding: 0.875rem 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.65rem;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #4F46E5; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold;">
                                        <?= e($initials) ?>
                                    </div>
                                    <div>
                                        <a href="<?= url('/hr/employees/' . $emp['id']) ?>" style="font-weight: 600; color: #1E293B; text-decoration: none;">
                                            <?= e($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                        </a>
                                        <div style="font-size: 0.75rem; color: #64748B;"><?= e($emp['gender']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 0.875rem 1.25rem;">
                                <div style="color: #334155;"><?= e($emp['email']) ?></div>
                                <div style="font-size: 0.75rem; color: #94A3B8;"><?= e($emp['phone'] ?: 'No phone') ?></div>
                            </td>
                            <td style="padding: 0.875rem 1.25rem;">
                                <div style="font-weight: 500; color: #1E293B;"><?= e($emp['department_name'] ?? 'Unassigned') ?></div>
                                <div style="font-size: 0.75rem; color: #64748B;"><?= e($emp['position_name'] ?? 'Unassigned') ?></div>
                            </td>
                            <td style="padding: 0.875rem 1.25rem; text-align: center;">
                                <?php if ($emp['employment_status'] === 'Active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.875rem 1.25rem; color: #475569;">
                                <?= e($emp['date_hired']) ?>
                            </td>
                            <td style="padding: 0.875rem 1.25rem; text-align: right;">
                                <div class="action-btns" style="justify-content: flex-end;">
                                    <a href="<?= url('/hr/employees/' . $emp['id']) ?>" class="btn btn-secondary btn-sm" title="View Profile">
                                        👁️ View
                                    </a>
                                    <a href="<?= url('/hr/employees/' . $emp['id'] . '/edit') ?>" class="btn btn-secondary btn-sm" title="Edit Employee">
                                        ✏️ Edit
                                    </a>
                                    <?php if ($emp['employment_status'] === 'Active'): ?>
                                        <button type="button" 
                                                class="btn btn-danger btn-sm" 
                                                onclick="confirmDeactivation(<?= e($emp['id']) ?>, '<?= e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])) ?>', '<?= e($emp['employee_no']) ?>')" 
                                                title="Deactivate Employee">
                                            🛑 Deactivate
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="padding: 3.5rem 1rem; text-align: center; color: #94A3B8;">
                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">👥</div>
                            <div style="font-size: 1.1rem; font-weight: 600; color: #475569;">No Matching Employee Records Found</div>
                            <p style="margin: 0.25rem 0 1.25rem 0; font-size: 0.875rem;">
                                <?= !empty($searchQuery) ? "No employees match your search query '<strong>" . e($searchQuery) . "</strong>'." : "There are currently no employees registered in this category." ?>
                            </p>
                            <?php if (!empty($searchQuery) || !empty($selectedDept) || !empty($selectedStatus)): ?>
                                <a href="<?= url('/hr/employees') ?>" class="btn btn-secondary btn-sm">Clear All Filters</a>
                            <?php else: ?>
                                <a href="<?= url('/hr/employees/create') ?>" class="btn btn-primary btn-sm">Register First Employee</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Soft-Deactivation Confirmation Modal -->
<div id="deactivateModal" class="modal-overlay" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-card">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: #FEE2E2; color: #DC2626; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">⚠️</div>
            <div>
                <h3 id="modalTitle" style="margin: 0; font-size: 1.15rem; color: #0F172A;">Confirm Employee Deactivation</h3>
                <div style="font-size: 0.8125rem; color: #64748B;">Soft deactivation preserves all historical leave & audit logs.</div>
            </div>
        </div>

        <p style="font-size: 0.875rem; color: #475569; line-height: 1.5; margin-bottom: 1.5rem;">
            Are you sure you want to change the employment status of <strong id="deactivateEmpName"></strong> (<code id="deactivateEmpNo"></code>) to <strong>Inactive</strong>?
            <br><br>
            <span style="font-size: 0.8125rem; color: #94A3B8;">
                Note: Inactive employees are blocked from system login. Historical leave balance and activity logs will NOT be deleted.
            </span>
        </p>

        <form id="deactivateForm" method="POST" action="" style="margin: 0; display: flex; justify-content: flex-end; gap: 0.75rem;">
            <?= csrf_field() ?>
            <button type="button" class="btn btn-secondary" onclick="closeDeactivateModal()">Cancel</button>
            <button type="submit" class="btn btn-danger" style="font-weight: 600;">Confirm Deactivation</button>
        </form>
    </div>
</div>

<script>
    function confirmDeactivation(id, name, empNo) {
        document.getElementById('deactivateEmpName').textContent = name;
        document.getElementById('deactivateEmpNo').textContent = empNo;
        document.getElementById('deactivateForm').action = '<?= url("/hr/employees/") ?>' + id + '/deactivate';
        document.getElementById('deactivateModal').style.display = 'flex';
    }

    function closeDeactivateModal() {
        document.getElementById('deactivateModal').style.display = 'none';
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeactivateModal();
        }
    });
</script>
