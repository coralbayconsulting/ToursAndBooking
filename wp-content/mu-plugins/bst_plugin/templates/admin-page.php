<div class="wrap">
    <h1>Settings</h1>
    <form action="options.php" method="post">
        <?php
        settings_fields('bst_settings_group');
        echo '<table class="form-table">';
        do_settings_fields('bst_settings_page', 'bst_settings_section');
        echo '</table>';
        
        echo '<h2>Email Integration</h2>';
        echo '<table class="form-table">';
        do_settings_fields('bst_settings_page', 'bst_gmail_section');
        echo '</table>';

        echo '<h2>SEO &amp; Schema</h2>';
        echo '<p class="description" style="margin-bottom:10px;">Controls the Organization schema output on the homepage and social sharing metadata. Individual tour and tour-type SEO overrides are set on each post using the SEO fields in the editor.</p>';
        echo '<table class="form-table">';
        do_settings_fields('bst_settings_page', 'bst_seo_section');
        echo '</table>';

        ?>
        <h2>Package Settings</h2>
        <table class="form-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Package 1</th>
                    <th>Package 2</th>
                    <th>Package 3</th>
                    <th>Package 4</th>
                    <th>Package 5</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row">Name</th>
                    <td><input type="text" name="bst_package_1_name" value="<?php echo esc_attr(get_option('bst_package_1_name')); ?>"></td>
                    <td><input type="text" name="bst_package_2_name" value="<?php echo esc_attr(get_option('bst_package_2_name')); ?>"></td>
                    <td><input type="text" name="bst_package_3_name" value="<?php echo esc_attr(get_option('bst_package_3_name')); ?>"></td>
                    <td><input type="text" name="bst_package_4_name" value="<?php echo esc_attr(get_option('bst_package_4_name')); ?>"></td>
                    <td><input type="text" name="bst_package_5_name" value="<?php echo esc_attr(get_option('bst_package_5_name')); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">People</th>
                    <td><input type="number" name="bst_package_1_people" value="<?php echo esc_attr(get_option('bst_package_1_people')); ?>"></td>
                    <td><input type="number" name="bst_package_2_people" value="<?php echo esc_attr(get_option('bst_package_2_people')); ?>"></td>
                    <td><input type="number" name="bst_package_3_people" value="<?php echo esc_attr(get_option('bst_package_3_people')); ?>"></td>
                    <td><input type="number" name="bst_package_4_people" value="<?php echo esc_attr(get_option('bst_package_4_people')); ?>"></td>
                    <td><input type="number" name="bst_package_5_people" value="<?php echo esc_attr(get_option('bst_package_5_people')); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">Rooms</th>
                    <td><input type="number" name="bst_package_1_rooms" value="<?php echo esc_attr(get_option('bst_package_1_rooms')); ?>"></td>
                    <td><input type="number" name="bst_package_2_rooms" value="<?php echo esc_attr(get_option('bst_package_2_rooms')); ?>"></td>
                    <td><input type="number" name="bst_package_3_rooms" value="<?php echo esc_attr(get_option('bst_package_3_rooms')); ?>"></td>
                    <td><input type="number" name="bst_package_4_rooms" value="<?php echo esc_attr(get_option('bst_package_4_rooms')); ?>"></td>
                    <td><input type="number" name="bst_package_5_rooms" value="<?php echo esc_attr(get_option('bst_package_5_rooms')); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">Vehicles</th>
                    <td><input type="number" name="bst_package_1_vehicles" value="<?php echo esc_attr(get_option('bst_package_1_vehicles')); ?>"></td>
                    <td><input type="number" name="bst_package_2_vehicles" value="<?php echo esc_attr(get_option('bst_package_2_vehicles')); ?>"></td>
                    <td><input type="number" name="bst_package_3_vehicles" value="<?php echo esc_attr(get_option('bst_package_3_vehicles')); ?>"></td>
                    <td><input type="number" name="bst_package_4_vehicles" value="<?php echo esc_attr(get_option('bst_package_4_vehicles')); ?>"></td>
                    <td><input type="number" name="bst_package_5_vehicles" value="<?php echo esc_attr(get_option('bst_package_5_vehicles')); ?>"></td>
                </tr>
            </tbody>
        </table>
        
        <?php
        submit_button('Save Settings');
        ?>
    </form>
</div>