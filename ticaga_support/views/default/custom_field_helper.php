<?php
/**
 * Renders a single Ticaga custom-field definition as a Bootstrap form group for
 * the ticket submission forms. Self-contained (no Blesta view helpers) so it can
 * be required from any .pdt view.
 *
 * @param array $cf A field definition: slug, name, type, options, required,
 *                  placeholder, description
 * @return string HTML for the field's form group
 */
if (!function_exists('ticaga_render_custom_field')) {
    function ticaga_render_custom_field(array $cf): string
    {
        $e = function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };

        $slug  = $cf['slug'] ?? '';
        $type  = $cf['type'] ?? 'text';
        $label = $cf['name'] ?? $slug;
        $req   = !empty($cf['required']);
        $ph    = $cf['placeholder'] ?? '';
        $desc  = $cf['description'] ?? '';
        $opts  = (isset($cf['options']) && is_array($cf['options'])) ? $cf['options'] : [];
        $fname = 'custom_fields[' . $slug . ']';
        $fid   = 'cf_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $slug);
        $reqAttr = $req ? ' required' : '';

        ob_start();
        ?>
        <div class="form-group">
            <label for="<?php echo $e($fid); ?>"><?php echo $e($label); ?><?php echo $req ? ' <span class="text-danger">*</span>' : ''; ?></label>
            <?php if (in_array($type, ['dropdown', 'radio'], true)) { ?>
                <select id="<?php echo $e($fid); ?>" name="<?php echo $e($fname); ?>" class="form-control"<?php echo $reqAttr; ?>>
                    <option value="">&mdash;</option>
                    <?php foreach ($opts as $opt) { ?>
                        <option value="<?php echo $e($opt); ?>"><?php echo $e($opt); ?></option>
                    <?php } ?>
                </select>
            <?php } elseif (in_array($type, ['checkbox', 'multiselect'], true)) { ?>
                <?php foreach ($opts as $i => $opt) { ?>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="<?php echo $e($fid . '_' . $i); ?>" name="<?php echo $e($fname); ?>[]" value="<?php echo $e($opt); ?>">
                        <label class="form-check-label" for="<?php echo $e($fid . '_' . $i); ?>"><?php echo $e($opt); ?></label>
                    </div>
                <?php } ?>
            <?php } elseif ($type === 'textarea') { ?>
                <textarea id="<?php echo $e($fid); ?>" name="<?php echo $e($fname); ?>" rows="3" class="form-control" placeholder="<?php echo $e($ph); ?>"<?php echo $reqAttr; ?>></textarea>
            <?php } else {
                $input_type = in_array($type, ['number', 'date', 'url', 'email', 'password'], true) ? $type : 'text';
            ?>
                <input type="<?php echo $e($input_type); ?>" id="<?php echo $e($fid); ?>" name="<?php echo $e($fname); ?>" class="form-control" placeholder="<?php echo $e($ph); ?>"<?php echo $reqAttr; ?>>
            <?php } ?>
            <?php if ($desc !== '') { ?>
                <small class="form-text text-muted"><?php echo $e($desc); ?></small>
            <?php } ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
