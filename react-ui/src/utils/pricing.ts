import { Bom, BomLine, Dealer } from '../types';

export interface BomTotals {
  parts: { retail: number; wholesale: number };
  labor: { retail: number; wholesale: number };
  shop: { retail: number; wholesale: number };
  total: { retail: number; wholesale: number };
  margin: number;
}

export function calculateBomTotals(
  lines: BomLine[],
  header: Bom,
  dealer: Dealer | null
): BomTotals {
  // 1. Initialize Totals
  let partsRetail = 0;
  let partsWholesale = 0;

  // 2. Calculate Line Items (Parts)
  for (const line of lines) {
    if (line.line_type === 'part') {
      // Rule 1: Parts Retail (SPRP)
      // Use override if present, else Vendor Retail * 1.10
      const unitRetail = line.override_retail ?? (line.unit_retail * 1.10);

      // Rule 2: Dealer Wholesale
      // Use override if present, else Calculated Retail * 0.80
      const unitWholesale = line.override_wholesale ?? (unitRetail * 0.80);

      partsRetail += (unitRetail * line.qty);
      partsWholesale += (unitWholesale * line.qty);
    }
  }

  // If no dealer selected, we can't calculate labor/shop rates accurately.
  // We'll return 0 for those or use a default if needed. 
  // For now, 0 is safer to indicate "Select a dealer".
  if (!dealer) {
    return {
      parts: { retail: partsRetail, wholesale: partsWholesale },
      labor: { retail: 0, wholesale: 0 },
      shop: { retail: 0, wholesale: 0 },
      total: { retail: partsRetail, wholesale: partsWholesale },
      margin: partsRetail - partsWholesale
    };
  }

  // 3. Calculate Labor
  // Rule 3: Labor Rates from Dealer Card
  const laborRetail = header.install_hours * dealer.labor_rate_retail;
  const laborWholesale = header.install_hours * dealer.labor_rate_wholesale;

  // 4. Calculate Shop Supplies
  // Rule 5: Shop Units * Dealer Rate
  const shopRetail = header.shop_supply_units * dealer.shop_supply_rate_retail;
  const shopWholesale = header.shop_supply_units * dealer.shop_supply_rate_wholesale;

  // 5. Installed Totals
  const installedRetail = partsRetail + laborRetail + shopRetail;
  const installedWholesale = partsWholesale + laborWholesale + shopWholesale;

  return {
    parts: { retail: partsRetail, wholesale: partsWholesale },
    labor: { retail: laborRetail, wholesale: laborWholesale },
    shop: { retail: shopRetail, wholesale: shopWholesale },
    total: { retail: installedRetail, wholesale: installedWholesale },
    margin: installedRetail - installedWholesale
  };
}
