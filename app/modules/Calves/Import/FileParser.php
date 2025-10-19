<?php
/**
 * File Parser
 * Handles parsing of CSV, Excel, and other file formats
 */
class FileParser {
    private $supportedExtensions = array('csv', 'xlsx', 'xls', 'txt', 'tsv');

    /**
     * Detect file type from extension
     */
    public function detectFileType($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $this->supportedExtensions)) {
            throw new Exception("Unsupported file type: $extension. Supported: " . implode(', ', $this->supportedExtensions));
        }
        
        return $extension;
    }

    /**
     * Parse file based on type
     */
    public function parseFile($filePath, $filename) {
        $fileType = $this->detectFileType($filename);
        
        switch ($fileType) {
            case 'csv':
            case 'txt':
                return $this->parseCSV($filePath);
            
            case 'tsv':
                return $this->parseTSV($filePath);
            
            case 'xlsx':
            case 'xls':
                // For now, treat as CSV and tell user to save as CSV
                throw new Exception("Excel files not supported yet. Please save as CSV and upload.");
            
            default:
                throw new Exception("File type not supported: $fileType");
        }
    }

    /**
     * Parse CSV file with smart delimiter detection
     */
    private function parseCSV($filePath) {
        $file = fopen($filePath, 'r');
        if (!$file) {
            throw new Exception("Could not open file: $filePath");
        }

        // Read first line to detect delimiter
        $firstLine = fgets($file);
        rewind($file);
        
        $delimiter = $this->detectDelimiter($firstLine);
        
        $data = array();
        $headers = null;
        
        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            if ($headers === null) {
                $headers = array_map('trim', $row);
            } else {
                $data[] = $row;
            }
        }
        
        fclose($file);
        
        return array(
            'headers' => $headers,
            'data' => $data
        );
    }

    /**
     * Parse TSV (tab-separated) file
     */
    private function parseTSV($filePath) {
        $file = fopen($filePath, 'r');
        if (!$file) {
            throw new Exception("Could not open file: $filePath");
        }

        $data = array();
        $headers = null;
        
        while (($row = fgetcsv($file, 0, "\t")) !== false) {
            if (empty(array_filter($row))) {
                continue;
            }
            
            if ($headers === null) {
                $headers = array_map('trim', $row);
            } else {
                $data[] = $row;
            }
        }
        
        fclose($file);
        
        return array(
            'headers' => $headers,
            'data' => $data
        );
    }

    /**
     * Detect CSV delimiter
     */
    private function detectDelimiter($line) {
        $delimiters = array(',', ';', '|', "\t");
        $delimiterCounts = array();
        
        foreach ($delimiters as $delimiter) {
            $delimiterCounts[$delimiter] = substr_count($line, $delimiter);
        }
        
        arsort($delimiterCounts);
        return key($delimiterCounts);
    }

    /**
     * Get list of supported file types
     */
    public function getSupportedFormats() {
        return $this->supportedExtensions;
    }
}
?>