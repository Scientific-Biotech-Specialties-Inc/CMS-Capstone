import ExcelJS from 'exceljs'

export interface ExcelColumn {
  header: string
  key: string
  width?: number
}

/**
 * Export structured data to an Excel (.xlsx) file using ExcelJS.
 * Automatically formats header with corporate styling and triggers browser download.
 *
 * @param filename File name for the download (e.g. 'contracts.xlsx')
 * @param sheetName Sheet tab name inside the workbook
 * @param columns Array of column header and key mappings
 * @param rows Array of row records
 */
export async function exportToExcel<T extends Record<string, any>>(
  filename: string,
  sheetName: string,
  rows: T[],
  columns?: ExcelColumn[]
): Promise<void> {
  const workbook = new ExcelJS.Workbook()
  const worksheet = workbook.addWorksheet(sheetName)

  if (rows.length === 0) {
    return
  }

  // Configure columns (use provided or auto-derive from first item keys)
  const resolvedColumns = columns && columns.length > 0
    ? columns
    : Object.keys(rows[0]).map(key => ({
        header: key,
        key: key,
        width: Math.max(key.length + 4, 18),
      }))

  worksheet.columns = resolvedColumns.map(c => ({
    header: c.header,
    key: c.key,
    width: c.width || 20,
  }))

  // Professional header row styling (Navy brand #252578)
  const headerRow = worksheet.getRow(1)
  headerRow.height = 28
  headerRow.font = {
    name: 'Poppins',
    size: 11,
    bold: true,
    color: { argb: 'FFFFFFFF' },
  }
  headerRow.fill = {
    type: 'pattern',
    pattern: 'solid',
    fgColor: { argb: 'FF252578' },
  }
  headerRow.alignment = {
    vertical: 'middle',
    horizontal: 'left',
  }

  // Populate data rows with padding and border styling
  rows.forEach(item => {
    const row = worksheet.addRow(item)
    row.height = 22
    row.font = { name: 'Poppins', size: 10 }
    row.alignment = { vertical: 'middle', horizontal: 'left' }
    row.eachCell({ includeEmpty: true }, cell => {
      cell.border = {
        bottom: { style: 'thin', color: { argb: 'FFE2E8F0' } },
        right: { style: 'thin', color: { argb: 'FFF1F5F9' } },
      }
    })
  })

  // Generate buffer and trigger browser download
  const buffer = await workbook.xlsx.writeBuffer()
  const blob = new Blob([buffer], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  })
  const url = window.URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename.endsWith('.xlsx') ? filename : `${filename}.xlsx`
  document.body.appendChild(anchor)
  anchor.click()
  document.body.removeChild(anchor)

  // Clean up object URL to prevent memory leaks
  window.URL.revokeObjectURL(url)
}
