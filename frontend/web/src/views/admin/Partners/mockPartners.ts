import type { Partner } from '@/types/partner'

export const initialBusinessPartners: Partner[] = [
  {
    id: 'BP-001',
    name: 'Philippine National Bank',
    industry: 'Banking & Finance',
    region: 'Luzon',
    status: 'Active',
    contracts: 2,
    totalValue: '₱3.2M',
    contactPerson: 'Jose Reyes',
    email: 'j.reyes@pnb.com.ph',
    phone: '+63 2 8573 8888',
    address: 'PNB Financial Center, Roxas Blvd, Pasay City',
    linkedContracts: [
      {
        associationId: '1',
        contractId: 'CON-2026-001',
        description: 'Core Banking IT Infrastructure Services',
        businessPartner: 'Philippine National Bank',
        startDate: '2026-01-01',
        endDate: '2027-01-01',
        engagementStatus: 'active',
        attachedBy: 'John Doe (Manager)'
      },
      {
        associationId: '2',
        contractId: 'CON-2026-002',
        description: 'Cash Management System Integration Upgrade',
        businessPartner: 'Philippine National Bank',
        startDate: '2026-03-01',
        endDate: '2027-03-01',
        engagementStatus: 'active',
        attachedBy: 'John Doe (Manager)'
      },
      {
        associationId: '3',
        contractId: 'CON-2025-001',
        description: 'ATM Maintenance Agreement Phase 1',
        businessPartner: 'Philippine National Bank',
        startDate: '2025-01-01',
        endDate: '2025-12-31',
        engagementStatus: 'expired',
        attachedBy: 'Jane Smith (Sales)'
      }
    ]
  },
  { id: 'BP-002', name: 'BDO Unibank', industry: 'Banking & Finance', region: 'Luzon', status: 'Active', contracts: 1, totalValue: '₱1.8M', contactPerson: 'Maria Dela Cruz', email: 'm.delacruz@bdo.com.ph', phone: '+63 2 8840 7000', address: 'BDO Corporate Center, Makati City', linkedContracts: [] },
  { id: 'BP-003', name: 'Cebu Pacific Air', industry: 'Aviation & Transport', region: 'Visayas', status: 'Active', contracts: 1, totalValue: '₱0.9M', contactPerson: 'Anna Santos', email: 'a.santos@cebupacificair.com', phone: '+63 32 230 8888', address: 'Gokongwei Building, Pasay City', linkedContracts: [] },
  { id: 'BP-004', name: 'SM Prime Holdings', industry: 'Real Estate & Retail', region: 'Luzon', status: 'Active', contracts: 0, totalValue: '₱3.2M', contactPerson: 'Carlos Tan', email: 'c.tan@smprime.com', phone: '+63 2 8831 1000', address: 'Mall of Asia Complex, Pasay City', linkedContracts: [] },
  { id: 'BP-005', name: 'Metrobank', industry: 'Banking & Finance', region: 'Mindanao', status: 'Active', contracts: 1, totalValue: '₱1.5M', contactPerson: 'Luis Garcia', email: 'l.garcia@metrobank.com.ph', phone: '+63 82 226 3891', address: 'Metrobank Plaza, Davao City', linkedContracts: [] },
  { id: 'BP-006', name: 'Globe Telecom', industry: 'Telecommunications', region: 'Luzon', status: 'Active', contracts: 1, totalValue: '₱4.5M', contactPerson: 'Rachel Lim', email: 'r.lim@globe.com.ph', phone: '+63 2 7730 1000', address: 'The Globe Tower, Bonifacio Global City', linkedContracts: [] },
  { id: 'BP-007', name: 'Philippine Airlines', industry: 'Aviation & Transport', region: 'Visayas', status: 'Active', contracts: 1, totalValue: '₱2.1M', contactPerson: 'Miguel Torres', email: 'm.torres@philippineairlines.com', phone: '+63 2 8855 8888', address: 'Andrews Avenue, Pasay City', linkedContracts: [] },
  { id: 'BP-008', name: 'PLDT', industry: 'Telecommunications', region: 'Mindanao', status: 'Active', contracts: 1, totalValue: '₱5.8M', contactPerson: 'Sara Uy', email: 's.uy@pldt.com.ph', phone: '+63 2 8816 8888', address: 'PLDT-Smart Tower, Makati City', linkedContracts: [] },
]

export const initialSuppliersData: Partner[] = [
  {
    id: 'SP-001',
    name: 'MedLine Philippines',
    industry: 'Medical Supplies',
    region: 'Luzon',
    status: 'Active',
    contracts: 3,
    totalValue: '₱2.1M',
    contactPerson: 'Dr. Elena Ramos',
    email: 'e.ramos@medline.ph',
    phone: '+63 2 8123 4567',
    address: '123 Bonifacio St., Mandaluyong City',
    linkedContracts: [
      {
        associationId: '4',
        contractId: 'CON-2026-003',
        description: 'Personal Protective Equipment Supply',
        businessPartner: 'MedLine Philippines',
        startDate: '2026-05-01',
        endDate: '2026-06-30',
        engagementStatus: 'expiring',
        attachedBy: 'John Doe (Manager)'
      }
    ]
  },
  { id: 'SP-002', name: 'Bio-Tech Logistics', industry: 'Logistics', region: 'Luzon', status: 'Active', contracts: 2, totalValue: '₱1.3M', contactPerson: 'Ryan Cruz', email: 'r.cruz@biotech-log.com', phone: '+63 2 8987 6543', address: '456 Ortigas Ave., Pasig City', linkedContracts: [] },
  { id: 'SP-003', name: 'Global Pharma Inc.', industry: 'Pharmaceutical', region: 'Visayas', status: 'Active', contracts: 1, totalValue: '₱0.8M', contactPerson: 'Dr. Peter Go', email: 'p.go@globalpharma.com', phone: '+63 32 412 3456', address: '789 Colon St., Cebu City', linkedContracts: [] },
  { id: 'SP-004', name: 'Stellar Lab Equipment', industry: 'Equipment', region: 'Luzon', status: 'Inactive', contracts: 0, totalValue: '₱1.5M', contactPerson: 'Nina Bautista', email: 'n.bautista@stellarlab.com', phone: '+63 2 8765 4321', address: '321 Science Drive, Quezon City', linkedContracts: [] },
  { id: 'SP-005', name: 'BioGenesis Research', industry: 'Research', region: 'Mindanao', status: 'Active', contracts: 2, totalValue: '₱3.8M', contactPerson: 'Dr. James Molo', email: 'j.molo@biogenesis.ph', phone: '+63 82 300 1234', address: '55 Quimpo Blvd., Davao City', linkedContracts: [] },
  { id: 'SP-006', name: 'PharmaCare Dist.', industry: 'Pharmaceutical', region: 'Luzon', status: 'Active', contracts: 1, totalValue: '₱2.5M', contactPerson: 'Lyn Navarro', email: 'l.navarro@pharmacare.ph', phone: '+63 2 8234 5678', address: '88 Shaw Blvd., Mandaluyong City', linkedContracts: [] },
  { id: 'SP-007', name: 'LabTech Solutions', industry: 'Equipment', region: 'Visayas', status: 'Active', contracts: 1, totalValue: '₱1.2M', contactPerson: 'Mark Villanueva', email: 'm.villanueva@labtech.com', phone: '+63 33 509 8765', address: '77 Iznart St., Iloilo City', linkedContracts: [] },
  { id: 'SP-008', name: 'MediSource PH', industry: 'Medical Supplies', region: 'Mindanao', status: 'Inactive', contracts: 0, totalValue: '₱0.7M', contactPerson: 'Donna Flores', email: 'd.flores@medisource.ph', phone: '+63 88 857 6543', address: '12 Cagayan de Oro City', linkedContracts: [] },
]
// Mock data removed — Partners and Suppliers now load from the vendor management service.
