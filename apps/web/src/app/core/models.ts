export interface ApiResponse<T> {
  success: boolean;
  data: T;
  message?: string;
  errors?: Record<string, string[]>;
  meta?: PageMeta;
}

export interface PageMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface Category {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  image_url: string | null;
  sort_order: number;
  is_active: boolean;
  meta_title: string | null;
  meta_description: string | null;
  product_count?: number;
}

export interface ProductOption {
  id: string;
  label: string;
  /** Price in minor units (cents). */
  price: number;
  serves: string | null;
  is_default: boolean;
}

export interface Product {
  id: string;
  name: string;
  slug: string;
  short_description: string | null;
  description: string | null;
  image_url: string | null;
  gallery: string[];
  /** Lowest option price in minor units (cents). */
  price_from: number;
  spice_level: number;
  min_quantity: number;
  is_featured: boolean;
  is_available: boolean;
  lead_time_hours: number;
  tags: string[];
  category: Pick<Category, 'id' | 'name' | 'slug'> | null;
  options: ProductOption[];
  meta_title: string | null;
  meta_description: string | null;
}

export interface CartLine {
  product_id: string;
  option_id: string;
  slug: string;
  name: string;
  option_label: string;
  image_url: string | null;
  unit_price: number;
  quantity: number;
  min_quantity: number;
}

export type FulfilmentMethod = 'pickup' | 'delivery';

export interface StoreSettings {
  currency: string;
  tax_rate: number;
  tax_label: string;
  delivery_fee: number;
  free_delivery_threshold: number | null;
  delivery_areas: string[];
  min_lead_hours: number;
  pickup_days: number[];
  time_slots: string[];
  pickup_address: string;
  phone: string;
  email: string;
  whatsapp: string | null;
  instagram: string | null;
  facebook: string | null;
  google_reviews_url: string | null;
}

export interface CheckoutPayload {
  items: { product_id: string; option_id: string; quantity: number }[];
  customer: { first_name: string; last_name: string; email: string; phone: string };
  fulfilment: {
    method: FulfilmentMethod;
    date: string;
    time_slot: string;
    address_line1?: string;
    address_line2?: string;
    city?: string;
    postal_code?: string;
  };
  notes?: string;
  coupon_code?: string;
}

export interface CheckoutResult {
  order_number: string;
  checkout_url: string;
}

export interface OrderItem {
  name: string;
  option_label: string;
  unit_price: number;
  quantity: number;
  line_total: number;
}

export type OrderStatus =
  | 'pending_payment'
  | 'paid'
  | 'preparing'
  | 'ready'
  | 'completed'
  | 'cancelled'
  | 'refunded';

export interface Order {
  id: string;
  order_number: string;
  status: OrderStatus;
  customer_first_name: string;
  customer_last_name: string;
  customer_email: string;
  customer_phone: string;
  fulfilment_method: FulfilmentMethod;
  fulfilment_date: string;
  fulfilment_time_slot: string;
  delivery_address: string | null;
  notes: string | null;
  subtotal: number;
  discount: number;
  delivery_fee: number;
  tax: number;
  total: number;
  currency: string;
  items: OrderItem[];
  created_at: string;
  paid_at: string | null;
}

export interface User {
  id: string;
  email: string;
  first_name: string;
  last_name: string;
  phone: string | null;
  role: 'customer' | 'admin';
}

export interface AuthTokens {
  access_token: string;
  refresh_token: string;
  expires_in: number;
  user: User;
}

export interface Review {
  author: string;
  rating: number;
  text: string;
  when: string;
}
