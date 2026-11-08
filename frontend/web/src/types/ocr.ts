export interface OcrExtractedData {
  business_partner: string;
  category: 'Service Agreement' | 'Partnership Agreement' | 'Supply Contract' | 'Equipment Lease' | 'Equipment Maintenance' | '';
  item_code: string;
  description: string;
  serial_number: string;
  sbu_number: string;
  region: 'Luzon' | 'Visayas' | 'Mindanao' | 'Unknown' | '';
  start_date: string;
  end_date: string;
  confidence_score: number;
}
