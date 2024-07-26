<?php

/**
 * Manages property listings, including retrieval of listing counts and generation of location-based buttons.
 */
class PropertyListingManager {

    /**
     * Retrieves the count of property listings for a given location and property types.
     *
     * @param string $location The location to query.
     * @param array $propertyTypes An array of property type IDs.
     * @return int The count of property listings.
     */
    public static function get_property_listing_count($location, $propertyTypes) {
        global $wpdb;
        $propertyTypesJson = wp_json_encode($propertyTypes);
        $sql = $wpdb->prepare(
            "SELECT count FROM {$wpdb->prefix}property_listing_counts WHERE location = %s AND propertyTypes = %s",
            $location,
            $propertyTypesJson
        );
        return (int) $wpdb->get_var($sql);
    }

    /**
     * Generates HTML buttons for property listings, grouped by location and optionally by the first letter of each location.
     *
     * @param array $propertyTypeIds An array of property type IDs.
     * @param string $type The type of grouping (e.g., 'zip', 'tract').
     * @return string HTML content of the generated buttons.
     */
    public static function generate_location_buttons($propertyTypeIds, $type = 'zip') {
        global $wpdb;
        $propertyTypesJson = wp_json_encode($propertyTypeIds);
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT location, count, lastUpdated FROM {$wpdb->prefix}property_listing_counts WHERE propertyTypes = %s AND type = %s ORDER BY location ASC",
                $propertyTypesJson,
                $type
            )
        );

        $html = '<div class="locations-with-counts ' . esc_attr($type) . '">';
        list($oldestLastUpdated, $newestLastUpdated, $html) = self::process_results($results, $type, $propertyTypeIds);

        $html .= self::generate_footnote($oldestLastUpdated, $newestLastUpdated);
        return $html;
    }

    /**
     * Constructs query parameters for URLs based on the type of listing and location.
     *
     * @param string $type The type of listing (e.g., 'zip', 'tract', 'county').
     * @param string $location The location identifier.
     * @param array $propertyTypeIds An array of property type IDs.
     * @return array An associative array of query parameters.
     */
    public static function construct_query_params($type, $location, $propertyTypeIds) {
        $queryParams = ["idx-q-$type" => $location];
        foreach ($propertyTypeIds as $i => $propertyTypeId) {
            $queryParams["idx-q-PropertyTypes<$i>"] = $propertyTypeId;
        }
        return $queryParams;
    }

    /**
     * Fetches all property types from the database.
     *
     * @return array An array of property types.
     */
    public static function fetch_all_property_types() {
        global $wpdb;
        $tableName = $wpdb->prefix . 'dsidx_property_types';
        $query = "SELECT * FROM $tableName";
        $propertyTypes = $wpdb->get_results($query);
        if ($wpdb->last_error) {
            die("Database error: " . $wpdb->last_error);
        }
        return $propertyTypes;
    }

    /**
     * Helper method to process results and generate HTML content.
     *
     * @param array $results Query results.
     * @param string $type The type of grouping.
     * @param array $propertyTypeIds An array of property type IDs.
     * @return array Contains oldest and newest last updated timestamps, and HTML content.
     */
    private static function process_results($results, $type, $propertyTypeIds) {
        $html = '';
        $oldestLastUpdated = null;
        $newestLastUpdated = null;
        $currentGroup = '';

        foreach ($results as $row) {
            if ($row->count === 0) {
                continue;
            }
            list($oldestLastUpdated, $newestLastUpdated, $html, $currentGroup) = self::update_html_content($row, $type, $propertyTypeIds, $oldestLastUpdated, $newestLastUpdated, $html, $currentGroup);
        }

        if ($type === 'tract' && !empty($currentGroup)) {
            $html .= '</details>';
        }

        return [$oldestLastUpdated, $newestLastUpdated, $html];
    }
    /**
     * Updates the HTML content with property listing information.
     *
     * This method groups property listings by their location's first character (digit or letter)
     * and generates HTML content to display these groups with details.
     *
     * @param stdClass $row Object containing property listing data.
     * @param string $type Type of the property listing (e.g., 'tract').
     * @param array $propertyTypeIds Array of property type IDs for query parameters.
     * @param int|null $oldestLastUpdated Timestamp of the oldest update.
     * @param int|null $newestLastUpdated Timestamp of the newest update.
     * @param string $html Current HTML content.
     * @param string $currentGroup The current group being processed.
     * @return array Updated values for oldest and newest last updated timestamps, HTML content, and current group.
     */
    private static function update_html_content($row, $type, $propertyTypeIds, $oldestLastUpdated, $newestLastUpdated, $html, $currentGroup) {
        // Convert lastUpdated string to timestamp and update oldest/newest timestamps
        $lastUpdated = strtotime($row->lastUpdated);
        $oldestLastUpdated = is_null($oldestLastUpdated) ? $lastUpdated : min($oldestLastUpdated, $lastUpdated);
        $newestLastUpdated = is_null($newestLastUpdated) ? $lastUpdated : max($newestLastUpdated, $lastUpdated);
        
        // Determine the group based on the first character of the location
        $group = self::determine_group($row->location);

        // Apply specific classes for each type and use <details>/<summary> for tracts
        if ($type === 'tract' && $group !== $currentGroup) {
            $html .= !empty($currentGroup) ? '</details>' : '';
            $html .= "<details class='locations-with-counts locations-with-counts.tract'><summary>$group</summary>";
            $currentGroup = $group;
        } elseif ($type !== 'tract' && $currentGroup !== $type) {
            $html .= !empty($currentGroup) ? '</div>' : '';
            $html .= "<div class='locations-with-counts locations-with-counts.$type'>";
            $currentGroup = $type;
        }

        // Construct query parameters and append the location button to the HTML
        $queryParams = self::construct_query_params($type, $row->location, $propertyTypeIds);
        $queryString = http_build_query($queryParams);
        $html .= sprintf(
            '<span class="btn-group zipcodes"><a href="/idx?%s" class="btn btn-default">%s (%d)</a></span>',
            $queryString,
            $row->location,
            $row->count
        );

        // Close the container for types other than 'tract'
        if ($type !== 'tract') {
            $html .= '</div>';
        }

        return [$oldestLastUpdated, $newestLastUpdated, $html, $currentGroup];
    }

    /**
     * Determines the group for a given location based on its first character.
     *
     * @param string $location The location to determine the group for.
     * @return string The determined group ('0-9', a letter, or 'Other').
     */
    private static function determine_group($location) {
        if (ctype_digit($location[0])) {
            return '0-9';
        }
        return ctype_alpha($location[0]) ? strtoupper($location[0]) : 'Other';
    }

    /**
     * Generates the footnote HTML content.
     *
     * @param int|null $oldestLastUpdated The oldest last updated timestamp.
     * @param int|null $newestLastUpdated The newest last updated timestamp.
     * @return string HTML content for the footnote.
     */
    private static function generate_footnote($oldestLastUpdated, $newestLastUpdated) {
        $html = '<div class="footnote">';
        if ($oldestLastUpdated !== null && $newestLastUpdated !== null) {
            $html .= '<span class="listing-count-note">* The number in parentheses represents the count of listings available in the corresponding location.</span> ';
            $html .= sprintf(
                '<span class="last-updated-note">The listings counts were last updated between %s and %s.</span>',
                date('F j, Y, g:i A', $oldestLastUpdated),
                date('F j, Y, g:i A', $newestLastUpdated)
            );
        } else {
            $html .= '<p class="footnote">* The number in parentheses represents the count of listings available in the corresponding location.</p>';
        }
        $html .= '</div>';
        return $html;
    }
}