<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    <!-- Add Category Form -->
    <div style="background: rgba(20, 20, 20, 0.8); padding: 25px; border-radius: 10px; border: 1px solid #333;">
        <h3 style="color: var(--accent-color); margin: 0 0 20px 0;">
            <i class="fas fa-plus"></i> Add New Category
        </h3>

        <form method="POST">
            <div style="margin-bottom: 15px;">
                <label style="display: block; color: #aaa; font-size: 0.9rem; margin-bottom: 5px;">Category Name</label>
                <input type="text" name="cat_name" required
                    style="width: 100%; padding: 10px; background: #111; border: 1px solid #333; color: #fff; border-radius: 5px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; color: #aaa; font-size: 0.9rem; margin-bottom: 5px;">Description</label>
                <textarea name="cat_description" rows="3"
                    style="width: 100%; padding: 10px; background: #111; border: 1px solid #333; color: #fff; border-radius: 5px; font-family: inherit;"></textarea>
            </div>

            <button type="submit" name="add_category" class="btn" style="width: 100%; padding: 10px;">
                <i class="fas fa-plus"></i> Add Category
            </button>
        </form>
    </div>

    <!-- Categories List -->
    <div style="background: rgba(20, 20, 20, 0.8); padding: 25px; border-radius: 10px; border: 1px solid #333;">
        <h3 style="color: var(--accent-color); margin: 0 0 20px 0;">
            <i class="fas fa-list"></i> All Categories (<?php echo count($categories); ?>)
        </h3>

        <?php if (empty($categories)): ?>
            <p style="color: #666; text-align: center; padding: 40px;">No categories yet</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <?php foreach ($categories as $cat): ?>
                    <div style="background: #111; padding: 15px; border-radius: 8px; border: 1px solid #333;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <div style="flex: 1;">
                                <h4 style="margin: 0 0 5px 0; color: #fff; font-size: 1.1rem;">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </h4>
                                <p style="margin: 0; color: #666; font-size: 0.85rem;">
                                    <?php echo htmlspecialchars($cat['description'] ?: 'No description'); ?>
                                </p>
                            </div>
                            <span
                                style="background: rgba(33, 150, 243, 0.2); color: #2196f3; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; white-space: nowrap; margin-left: 15px;">
                                <?php echo $cat['media_count']; ?> media
                            </span>
                        </div>

                        <a href="?delete_category=<?php echo $cat['id']; ?>"
                            onclick="return confirmAction(event, 'Delete Category', 'Delete this category? Media will not be deleted.')"
                            class="btn"
                            style="padding: 6px 12px; font-size: 0.85rem; background: #f44336; text-decoration: none; display: inline-block;">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>