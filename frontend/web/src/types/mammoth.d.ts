declare module 'mammoth' {
  export interface ConversionOptions {
    styleMap?: string[];
    includeDefaultStyleMap?: boolean;
    includeEmbeddedStyleMap?: boolean;
    ignoreEmptyParagraphs?: boolean;
    idPrefix?: string;
  }

  export interface ConversionResult {
    value: string; // The generated HTML
    messages: any[]; // Any messages, such as warnings during conversion
  }

  export function convertToHtml(input: { arrayBuffer: ArrayBuffer }, options?: ConversionOptions): Promise<ConversionResult>;
}
