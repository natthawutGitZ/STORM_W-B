<div class="admin-grid">
    <!-- Add Tag Form -->
    <div class="glass-panel modern-form">
        <h3 style="color: var(--accent-color); margin: 0 0 20px 0;">
            <i class="fas fa-plus"></i> Add New Tag
        </h3>

        <form method="POST">
            <div class="form-group">
                <label>Tag Name</label>
                <input type="text" name="tag_name" required>
            </div>

            <button type="submit" name="add_tag" class="btn" style="width: 100%;">
                <i class="fas fa-plus"></i> Add Tag
            </button>
        </form>
    </div>

    <!-- Tags Grid -->
    <div class="glass-panel">
        <h3 style="color: var(--accent-color); margin: 0 0 20px 0;">
            <i class="fas fa-tags"></i> All Tags (<?php echo count($tags); ?>)
        </h3>

        <?php if (empty($tags)): ?>
            <p style="color: #666; text-align: center; padding: 40px;">No tags yet</p>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                <?php foreach ($tags as $tag): ?>
                    <div
                        style="background: rgba(255, 255, 255, 0.05); padding: 15px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.05);">
                        <div style="margin-bottom: 10px;">
                            <h4 style="margin: 0 0 5px 0; color: #fff; font-size: 1rem;">
                                #<?php echo htmlspecialchars($tag['name']); ?>
                            </h4>
                            <span style="color: #666; font-size: 0.75rem;">
                                <?php echo isset($tag['media_count']) ? $tag['media_count'] : 0; ?> media
                            </span>
                        </div>

                        <a href="?tab=tags&delete_tag_id=<?php echo $tag['id']; ?>"
                            onclick="return confirmAction(event, 'Delete Tag', 'Are you sure you want to delete this tag?')"
                            style="display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: rgba(244, 67, 54, 0.1); color: #f44336; text-decoration: none; border-radius: 5px; border: 1px solid rgba(244, 67, 54, 0.3); font-size: 0.8rem; transition: all 0.2s;">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>