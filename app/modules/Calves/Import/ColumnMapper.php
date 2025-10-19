<?php
/**
 * Column Mapper
 * Intelligently maps CSV/Excel columns to database fields
 */
class ColumnMapper {
    private $columnMappings = array(
        'calf_id' => array('calf_id', 'calfid', 'calf id', 'id', 'tag', 'tag number', 'tag_number', 'animal id', 'animal_id', 'eartag', 'ear tag'),
        'birth_date' => array('birth_date', 'birthdate', 'birth date', 'dob', 'date of birth', 'date_of_birth', 'born', 'born date', 'calving date'),
        'sex' => array('sex', 'gender', 'm/f', 'male/female'),
        'dam_id' => array('dam_id', 'damid', 'dam id', 'dam', 'mother', 'mother id', 'mother_id', 'cow id', 'cow_id', 'dam tag', 'dam_tag'),
        'birth_weight' => array('birth_weight', 'birthweight', 'birth weight', 'weight', 'weight at birth', 'bw'),
        'health_status' => array('health_status', 'health status', 'health', 'status', 'condition'),
        'breed' => array('breed', 'breed type', 'breed_type'),
        'batch_id' => array('batch_id', 'batch id', 'batch', 'group', 'pen'),
        'pen_location' => array('pen_location', 'pen location', 'pen', 'location', 'pen number', 'pen_number')
    );

    /**
     * Normalize column name for comparison
     */
    private function normalizeColumnName($columnName) {
        return strtolower(trim(str_replace(array('_', '-', '.'), ' ', $columnName)));
    }

    /**
     * Map CSV/Excel headers to database fields
     */
    public function mapColumns($headers) {
        $mappedColumns = array();
        
        foreach ($headers as $index => $header) {
            $normalizedHeader = $this->normalizeColumnName($header);
            
            // Try to find a match in our mappings
            foreach ($this->columnMappings as $dbField => $variations) {
                $normalizedVariations = array_map(array($this, 'normalizeColumnName'), $variations);
                
                if (in_array($normalizedHeader, $normalizedVariations)) {
                    $mappedColumns[$index] = $dbField;
                    break;
                }
            }
            
            // If no match found, store the original header name
            if (!isset($mappedColumns[$index])) {
                $mappedColumns[$index] = $normalizedHeader;
            }
        }
        
        return $mappedColumns;
    }

    /**
     * Validate that required fields are present
     */
    public function validateRequiredFields($mappedColumns) {
        $requiredFields = array('calf_id', 'birth_date', 'sex');
        $missingFields = array();
        
        foreach ($requiredFields as $field) {
            if (!in_array($field, $mappedColumns)) {
                $missingFields[] = $field;
            }
        }
        
        return array(
            'valid' => empty($missingFields),
            'missing_fields' => $missingFields
        );
    }
}
?>