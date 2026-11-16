<div style="max-width: 640px; margin: 0 auto;">
    <div style="margin-bottom: 1.5rem;">
        <a href="<?= url('/hr/departments') ?>" style="text-decoration: none; color: #4F46E5; font-size: 0.875rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem;">
            ← Back to Departments Directory
        </a>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--color-text-main, #1E293B); margin: 0.5rem 0 0.25rem 0;">Add New Department</h1>
        <p style="color: var(--color-text-muted, #64748B); margin: 0; font-size: 0.875rem;">Define a new organizational business unit or functional division.</p>
    </div>

    <div class="card" style="background: var(--color-bg-surface, #FFFFFF); border: 1px solid var(--color-border, #E2E8F0); border-radius: 0.75rem; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <form method="POST" action="<?= url('/hr/departments') ?>">
            <?= csrf_field() ?>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="deptName" class="form-label required" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: var(--color-text-main, #1E293B);">
                    Department Name
                </label>
                <input type="text" 
                       id="deptName" 
                       name="department_name" 
                       class="form-input" 
                       style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid <?= !empty($errors['department_name']) ? '#EF4444' : '#CBD5E1' ?>; border-radius: 0.5rem; font-size: 0.875rem; box-sizing: border-box;"
                       placeholder="e.g. Legal & Corporate Compliance" 
                       value="<?= e($old['department_name'] ?? '') ?>" 
                       required 
                       autofocus>
                <?php if (!empty($errors['department_name'])): ?>
                    <div style="color: #EF4444; font-size: 0.8125rem; margin-top: 0.25rem;">
                        <?= e($errors['department_name']) ?>
                    </div>
                <?php endif; ?>
                <small style="color: var(--color-text-muted, #94A3B8); font-size: 0.75rem; display: block; margin-top: 0.25rem;">Must be unique across the organization.</small>
            </div>

            <div class="form-group" style="margin-bottom: 1.75rem;">
                <label for="deptDesc" class="form-label" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: var(--color-text-main, #1E293B);">
                    Description (Optional)
                </label>
                <textarea id="deptDesc" 
                          name="description" 
                          class="form-input" 
                          rows="4" 
                          style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid var(--color-border-dark, #CBD5E1); border-radius: 0.5rem; font-size: 0.875rem; box-sizing: border-box; resize: vertical;"
                          placeholder="Briefly describe the mandate, responsibilities, or functional scope of this department..."><?= e($old['description'] ?? '') ?></textarea>
                <?php if (!empty($errors['description'])): ?>
                    <div style="color: #EF4444; font-size: 0.8125rem; margin-top: 0.25rem;">
                        <?= e($errors['description']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; border-top: 1px solid var(--color-border-subtle, #F1F5F9); padding-top: 1.25rem;">
                <a href="<?= url('/hr/departments') ?>" class="btn btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary" style="padding: 0.625rem 1.25rem; font-weight: 600;">
                    Save Department
                </button>
            </div>
        </form>
    </div>
</div>
