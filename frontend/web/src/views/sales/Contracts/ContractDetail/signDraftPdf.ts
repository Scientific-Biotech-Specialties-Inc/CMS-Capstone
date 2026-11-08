export interface SignaturePlacement {
  page: number
  x: number
  y: number
}

export const SIGNATURE_PAGE_WIDTH = 0.25

export async function signDraftPdf(draft: ArrayBuffer, signatureImage: string, placement: SignaturePlacement): Promise<File> {
  const { PDFDocument, degrees } = await import('pdf-lib')
  const pdf = await PDFDocument.load(draft)
  const page = pdf.getPage(placement.page - 1)
  const box = page.getCropBox()
  const rotation = (page.getRotation().angle % 360 + 360) % 360
  const sideways = rotation === 90 || rotation === 270
  const viewWidth = sideways ? box.height : box.width
  const viewHeight = sideways ? box.width : box.height
  const signature = await pdf.embedPng(signatureImage)
  const width = viewWidth * SIGNATURE_PAGE_WIDTH
  const height = width * signature.height / signature.width
  if (height > viewHeight) throw new Error('This signature image is too tall for the selected page.')

  const left = Math.max(0, Math.min(placement.x * viewWidth, viewWidth - width))
  const bottom = Math.max(0, Math.min((1 - placement.y) * viewHeight - height, viewHeight - height))
  const origin = rotation === 90 ? { x: box.width - bottom, y: left }
    : rotation === 180 ? { x: box.width - left, y: box.height - bottom }
      : rotation === 270 ? { x: bottom, y: box.height - left }
        : { x: left, y: bottom }

  page.drawImage(signature, { x: box.x + origin.x, y: box.y + origin.y, width, height, rotate: degrees(rotation) })
  const bytes = await pdf.save()
  return new File([new Uint8Array(bytes)], 'signed-contract.pdf', { type: 'application/pdf' })
}
