<?php

class Pizza {
    /**
     * Number of rows
     * @var integer
     * @protected
     */
    protected $iRows;

    /**
     * Number of columns
     * @var integer
     * @protected
     */
    protected $iColumns;

    /**
     * Minumun number of ingredients per slice
     * @var integer
     * @protected
     */
    protected $iMinIngredients;

    /**
     * Maximum number of cells per slice
     * @var integer
     * @protected
     */
    protected $iMaxCells;

    /**
     * Pizza matrix
     * @var array
     * @protected
     */
    protected $aPizza = array();

    /**
     * Pizza slices
     * @var array
     * @protected
     */
    protected $aSlices = array();

    /**
     * Constructor
     * @param   string  file    input file
     */
    public function __construct($sFile) {
        $aFile = file($sFile);
        list($this->iRows, $this->iColumns, $this->iMinIngredients, $this->iMaxCells) = explode(' ', $aFile[0]);
        for($i = 1; $i <= $this->iRows; $i++) {
            //let's trust the input file
            $this->aPizza[] = str_split($aFile[$i]);
        }
    }

    /**
     * Saves the slice and erase it from the matrix
     * @param   integer row1    start row
     * @param   integer column1 start column
     * @param   integer row2    end row
     * @param   integer column2 end column
     */
    protected function saveSlice($iRow1, $iColumn1, $iRow2, $iColumn2) {
        for($iRow = $iRow1; $iRow <= $iRow2; $iRow++) {
            for($iColumn = $iColumn1; $iColumn <= $iColumn2; $iColumn++) {
                $this->aPizza[$iRow][$iColumn] = null;
            }
        }

        $this->aSlices[] = $iRow1.' '.$iColumn1.' '.$iRow2.' '.$iColumn2;
    }

    /**
     * Check for slice validity
     * @param   integer row1    start row
     * @param   integer column1 start column
     * @param   integer row2    end row
     * @param   integer column2 end column
     * @return  boolean
     */
    protected function isValidSlice($iRow1, $iColumn1, $iRow2, $iColumn2) {
        if((($iRow2 - $iRow1) + 1) * (($iColumn2 - $iColumn1) + 1) > $this->iMaxCells) {
            return false;
        }

        $aIngredients = array('M' => 0, 'T' => 0);
        for($iRow = $iRow1; $iRow <= $iRow2; $iRow++) {
            for($iColumn = $iColumn1; $iColumn <= $iColumn2; $iColumn++) {
                if(is_null($this->aPizza[$iRow][$iColumn])) {
                    return false;
                }

                $aIngredients[$this->aPizza[$iRow][$iColumn]]++;
            }
        }

        return min($aIngredients) >= $this->iMinIngredients;
    }

    /**
     * Cuts pizza into slices
     * @return  string
     */
    public function cut() {
        for($iRow1 = 0; $iRow1 < $this->iRows; $iRow1++) {
            for($iColumn1 = 0; $iColumn1 < $this->iColumns; $iColumn1++) {
                //if it's an already used slot let's skip it
                if(is_null($this->aPizza[$iRow1][$iColumn1])) {
                    continue;
                }

                for($iRow2 = $iRow1; $iRow2 < $this->iRows; $iRow2++) {
                    //slices can't overlap
                    if(is_null($this->aPizza[$iRow2][$iColumn1])) {
                        break;
                    }

                    for($iColumn2 = $iColumn1; $iColumn2 < $this->iColumns; $iColumn2++) {
                        //slices can't overlap
                        if(is_null($this->aPizza[$iRow2][$iColumn2])) {
                            break;
                        }

                        if($this->isValidSlice($iRow1, $iColumn1, $iRow2, $iColumn2)) {
                            $this->saveSlice($iRow1, $iColumn1, $iRow2, $iColumn2);
                        }
                    }
                }
            }
        }

        return count($this->aSlices)."\n".implode("\n", $this->aSlices)."\n";
    }
}

$aFiles = array('/example.', '/small.', '/medium.', '/big.');
foreach($aFiles as $sFile) {
    $oPizza = new Pizza(__DIR__.$sFile.'in');
    file_put_contents(__DIR__.$sFile.'out', $oPizza->cut());
}
