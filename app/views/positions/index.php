<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: #1E293B; margin: 0 0 0.25rem 0;">Job Positions Directory</h1>
        <p style="color: #64748B; margin: 0; font-size: 0.875rem;">Manage job roles, titles, and departmental hierarchy alignments.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <!-- Department Filter -->
        <form method="GET" action="<?= url('/hr/positions') ?>" style="margin: 0;">
            <select name="dept_id" onchange="this.form.submit()" class="form-input" style="padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem; background: #FFFFFF;">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= e($dept['id']) ?>" <?= (!empty($selectedDeptId) && $selectedDeptId == $dept['id']) ? 'selected' : '' ?>>
                        <?= e($dept['department_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if (can('positions.manage')): ?>
            <a href="<?= url('/hr/positions/create') ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                <span>➕</span>
                <span>Add Position</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden;">
    <div class="table-responsive">
        <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
            <thead>
                <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; color: #475569; text-align: left;">
                    <th style="padding: 0.875rem 1.25rem; font-weight: 600;">ID</th>
                    <th style="padding: 0.875rem 1.25rem; font-weight: 600;">Position Title</th>
                    <th style="padding: 0.875rem 1.25rem; font-weight: 600;">Department</th>
                    <th style="padding: 0.875rem 1.25rem; font-weight: 600;">Description</th>
                    <th style="padding: 0.875rem 1.25rem; font-weight: 600; text-align: center;">Active Staff</th>
                    <th style="padding: 0.875rem 1.25rem; font-weight: 600; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($positions)): ?>
                    <?php foreach ($positions as $pos): ?>
                        <tr style="border-bottom: 1px solid #F1F5F9; transition: background 0.15s ease;">
                            <td style="padding: 0.875rem 1.25rem; color: #64748B;"><code>#<?= e($pos['id']) ?></code></td>
                            <td style="padding: 0.875rem 1.25rem;">
                                <strong style="color: #1E293B; font-size: 0.9375rem;"><?= e($pos['position_name']) ?></strong>
                            </td>
                            <td style="padding: 0.875rem 1.25rem;">
                                <span class="badge badge-info"><?= e($pos['department_name']) ?></span>
                            </td>
                            <td style="padding: 0.875rem 1.25rem; color: #475569; max-width: 320px;">
                                <?= e($pos['description'] ?: '—') ?>
                            </td>
                            <td style="padding: 0.875rem 1.25rem; text-align: center;">
                                <span class="badge badge-success"><?= e($pos['active_employee_count']) ?> active</span>
                            </td>
                            <td style="padding: 0.875rem 1.25rem; text-align: right;">
                                <div class="action-btns" style="justify-content: flex-end;">
                                    <?php if (can('positions.manage')): ?>
                                        <a href="<?= url('/hr/positions/' . $pos['id'] . '/edit') ?>" class="btn btn-secondary btn-sm" title="Edit Position">
                                            ✏️ Edit
                                        </a>
                                    <?php else: ?>
                                        <span class="badge badge-neutral">Read-Only</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding: 3rem 1rem; text-align: center; color: #94A3B8;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">💼</div>
                            <div style="font-size: 1rem; font-weight: 600; color: #475569;">No Job Positions Found</div>
                            <p style="margin: 0.25rem 0 1rem 0; font-size: 0.875rem;">
                                <?= !empty($selectedDeptId) ? 'No positions registered for this department.' : 'Get started by creating a position classification.' ?>
                            </p>
                            <a href="<?= url('/hr/positions/create') ?>" class="btn btn-primary btn-sm">Add Position</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
