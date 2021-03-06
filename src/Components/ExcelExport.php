<?php

namespace Nayjest\Grids\Components;

use Event;
use Maatwebsite\Excel\Classes\LaravelExcelWorksheet;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Writers\LaravelExcelWriter;
use Nayjest\Grids\Components\Base\RenderableComponent;
use Nayjest\Grids\Components\Base\RenderableRegistry;
use Nayjest\Grids\DataProvider;
use Nayjest\Grids\DataRow;
use Nayjest\Grids\FieldConfig;
use Nayjest\Grids\Grid;

/**
 * Class ExcelExport
 *
 * The component provides control for exporting data to excel.
 *
 * @author: Alexander Hofmeister
 * @package Nayjest\Grids\Components
 */
class ExcelExport extends RenderableComponent
{
    const NAME = 'excel_export';
    const INPUT_PARAM = 'xls';
    const DEFAULT_ROWS_LIMIT = 15000;

    protected $template = '*.components.excel_export';
    protected $name = ExcelExport::NAME;
    protected $render_section = RenderableRegistry::SECTION_END;
    protected $rows_limit = self::DEFAULT_ROWS_LIMIT;
    protected $extension = 'xls';

    /**
     * @var string
     */
    protected $output;

    /**
     * @var string
     */
    protected $fileName;

    /**
     * @var string
     */
    protected $sheetName;

    protected $ignored_columns = [];

    protected $is_hidden_columns_exported = false;

    /**
     * @param Grid $grid
     * @return null|void
     */
    public function initialize(Grid $grid)
    {
        parent::initialize($grid);
        Event::listen(Grid::EVENT_PREPARE, function (Grid $grid) {
            if ($this->grid !== $grid) {
                return;
            }
            if ($grid->getInputProcessor()->getValue(static::INPUT_PARAM, false)) {
                $this->renderExcel();
            }
        });
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setFileName($name)
    {
        $this->fileName = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return $this->fileName ?: $this->grid->getConfig()->getName();
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setSheetName($name)
    {
        $this->sheetName = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getSheetName()
    {
        return $this->sheetName;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setExtension($name)
    {
        $this->extension = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * @return int
     */
    public function getRowsLimit()
    {
        return $this->rows_limit;
    }

    /**
     * @param int $limit
     *
     * @return $this
     */
    public function setRowsLimit($limit)
    {
        $this->rows_limit = $limit;
        return $this;
    }

    protected function resetPagination(DataProvider $provider)
    {
        $provider->setPageSize($this->getRowsLimit());
        $provider->setCurrentPage(1);
    }

    /**
     * @param FieldConfig $column
     * @return bool
     */
    protected function isColumnExported(FieldConfig $column)
    {
        return !in_array($column->getName(), $this->getIgnoredColumns())
            && ($this->isHiddenColumnsExported() || !$column->isHidden())
            && $column->getName() != 'select_column'
            && $column->getName() != 'action_column';
    }

    protected function getData()
    {
        // Build array
        $exportData = [];
        /** @var $provider DataProvider */
        $provider = $this->grid->getConfig()->getDataProvider();

        $selected = $this->grid->getInputProcessor()->getSelectedRows();

        $exportData[] = $this->getHeaderRow();

        $this->resetPagination($provider);
        $provider->reset();
        /** @var DataRow $row */
        while ($row = $provider->getRow()) {
            if($selected == null || in_array($row->getSrc()->id, $selected)){
                $output = [];
                foreach ($this->grid->getConfig()->getColumns() as $column) {
                    if ($this->isColumnExported($column)) {
                        $value = $this->escapeString($column->getValue($row));
                        if($column->getFormat() == 'integer'){
                            $output[] = intval($value);
                        }
                        elseif($column->getFormat() == 'float'){
                            $output[] = floatval($value);
                        }
                        else{
                            $output[] = $value;
                        }
                    }
                }
                $exportData[] = $output;
            }
        }
        return $exportData;
    }

    protected function renderExcel()
    {
        $onSheetCreate = function (LaravelExcelWorksheet $sheet) {
            $sheet->fromArray($this->getData(), null, 'A1', false, false);
        };
        $onFileCreate = function (LaravelExcelWriter $excel) use ($onSheetCreate) {
            $excel->sheet($this->getSheetName(), $onSheetCreate);
        };

        /** @var Excel $excel */
        $excel = app('excel');
        $excel
            ->create($this->getFileName(), $onFileCreate)
            ->export($this->getExtension());
    }

    protected function escapeString($str)
    {
        return str_replace('"', '\'', strip_tags(html_entity_decode($str)));
    }

    protected function getHeaderRow()
    {
        $output = [];
        foreach ($this->grid->getConfig()->getColumns() as $column) {
            if ($this->isColumnExported($column)) {
                $output[] = $this->escapeString($column->getLabel());
            }
        }
        return $output;
    }

    /**
     * @return string[]
     */
    public function getIgnoredColumns()
    {
        return $this->ignored_columns;
    }

    /**
     * @param string[] $ignoredColumns
     * @return $this
     */
    public function setIgnoredColumns(array $ignoredColumns)
    {
        $this->ignored_columns = $ignoredColumns;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isHiddenColumnsExported()
    {
        return $this->is_hidden_columns_exported;
    }

    /**
     * @param bool $isHiddenColumnsExported
     * @return $this
     */
    public function setHiddenColumnsExported($isHiddenColumnsExported)
    {
        $this->is_hidden_columns_exported = $isHiddenColumnsExported;
        return $this;
    }
}
