export type Role = 'admin' | 'cashier' | 'kitchen' | 'rider' | 'customer';
export type OrderType = 'pickup' | 'delivery' | 'dine_in';
export type OrderSource = 'online' | 'pos';
export type OrderStatus =
    | 'pending_payment'
    | 'confirmed'
    | 'preparing'
    | 'ready'
    | 'out_for_delivery'
    | 'completed'
    | 'cancelled';
export type PaymentStatus = 'pending' | 'paid' | 'failed' | 'refunded';
export type PaymentMethod = 'fpx' | 'card' | 'ewallet' | 'cash';
export type KitchenItemStatus = 'queued' | 'cooking' | 'done';
export type DeliveryStatus = 'pending' | 'assigned' | 'picked_up' | 'delivered' | 'failed';
export type ProductStatus = 'available' | 'sold_out' | 'coming_soon' | 'hidden';
export type SyncStatus = 'pending' | 'synced' | 'failed';

export interface Option<T extends string | number = string> {
    value: T;
    label: string;
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: Role;
    role_label: string;
    is_staff: boolean;
    home_url: string;
}

export interface Shop {
    name: string;
    tagline: string;
    address: string;
    phone: string;
    whatsapp: string;
    maps_url: string;
    counter: string;
    hours: { open: string; close: string; last_order: string };
    is_open: boolean;
    closed_message: string | null;
    pickup_enabled: boolean;
    delivery_enabled: boolean;
}

export interface KitchenLoad {
    level: 'quiet' | 'normal' | 'busy' | 'slammed';
    label: string;
    active_orders: number;
    cooking_items: number;
    eta_min: number;
    eta_max: number;
}

export interface CartLine {
    id: string;
    product_id: number;
    code: string | null;
    name: string;
    image_url: string | null;
    variant_name: string | null;
    modifiers: { name: string; price: number }[];
    combo: { name: string; choice: string | null; price: number } | null;
    notes: string | null;
    quantity: number;
    unit_price: number;
    line_total: number;
}

export interface CartZone {
    id: number;
    name: string;
    fee: number;
    min_order: number;
    eta_minutes: number;
}

export interface Cart {
    lines: CartLine[];
    count: number;
    type: OrderType;
    zone: CartZone | null;
    subtotal: number;
    delivery_fee: number;
    tax_rate: number;
    tax_inclusive: boolean;
    tax_amount: number;
    total: number;
    min_order_shortfall: number;
    upsells: { id: number; name: string; price: number }[];
}

/** Result of the last order rung up on the counter POS. */
export interface PosReceipt {
    uuid: string;
    order_number: string;
    total: number;
    paid: boolean;
    method_label: string;
    change: number | null;
}

export interface SharedProps {
    auth: { user: AuthUser | null };
    flash: { success: string | null; error: string | null; receipt: PosReceipt | null };
    shop: Shop;
    kitchen: KitchenLoad;
    pos: { provider: string; connected: boolean };
    cart: Cart | null;
    realtime: boolean;
    [key: string]: unknown;
}

export interface Variant {
    id: number;
    name: string;
    price: number;
    is_default: boolean;
}

export interface Modifier {
    id: number;
    name: string;
    price: number;
    is_available: boolean;
}

export interface ModifierGroup {
    id: number;
    name: string;
    min_select: number;
    max_select: number;
    sort_order: number;
    products_count?: number;
    modifiers?: Modifier[];
}

export interface Product {
    id: number;
    category_id: number;
    kitchen_station_id: number | null;
    code: string | null;
    name: string;
    slug: string;
    description: string | null;
    image_url: string | null;
    price: number;
    from_price: number;
    status: ProductStatus;
    status_label: string;
    is_orderable: boolean;
    badge: string | null;
    tags: string[];
    is_combo_eligible: boolean;
    is_upsell: boolean;
    prep_minutes: number;
    sort_order: number;
    pos_linked: boolean;
    category?: { id: number; name: string; icon: string | null };
    variants?: Variant[];
    modifier_groups?: ModifierGroup[];
}

export interface Category {
    id: number;
    kitchen_station_id: number | null;
    name: string;
    slug: string;
    icon: string | null;
    eyebrow: string | null;
    tagline: string | null;
    sort_order: number;
    is_active: boolean;
    products_count?: number;
    products?: Product[];
}

export interface ComboOffer {
    id: number;
    name: string;
    description: string | null;
    price: number;
    choices: string[];
    is_active?: boolean;
}

export interface TimelineStep {
    key: string;
    label: string;
    detail: string;
    state: 'done' | 'active' | 'upcoming';
    at: string | null;
}

export interface OrderItem {
    id: number;
    code: string | null;
    name: string;
    variant_name: string | null;
    combo_name: string | null;
    combo_choice: string | null;
    combo_price: number;
    unit_price: number;
    quantity: number;
    line_total: number;
    notes: string | null;
    kitchen_status: KitchenItemStatus;
    kitchen_status_label: string;
    started_at: string | null;
    done_at: string | null;
    image_url: string | null;
    icon: string | null;
    station: { id: number; name: string } | null;
    modifiers: { name: string; price: number }[];
}

export interface OrderDelivery {
    id: number;
    status: DeliveryStatus;
    status_label: string;
    recipient_name: string;
    phone: string;
    address: string;
    notes: string | null;
    fee: number;
    zone: string | null;
    eta_minutes: number | null;
    assigned_at: string | null;
    picked_up_at: string | null;
    delivered_at: string | null;
    rider: { id: number; name: string; phone: string | null; vehicle_type: string; plate_number: string | null } | null;
}

export interface Order {
    id: number;
    uuid: string;
    order_number: string;
    pickup_code: string;
    type: OrderType;
    type_label: string;
    source: OrderSource;
    source_label: string;
    status: OrderStatus;
    status_label: string;
    is_final: boolean;
    payment_status: PaymentStatus;
    payment_status_label: string;
    payment_method: PaymentMethod;
    payment_method_label: string;
    is_paid: boolean;
    customer_name: string;
    /** Null for walk-in counter orders taken without a phone number. */
    customer_phone: string | null;
    customer_email: string | null;
    notes: string | null;
    subtotal: number;
    delivery_fee: number;
    discount: number;
    tax_amount: number;
    tax_rate: number;
    tax_inclusive: boolean;
    total: number;
    cancel_reason: string | null;
    created_at: string;
    estimated_ready_at: string | null;
    confirmed_at: string | null;
    preparing_at: string | null;
    ready_at: string | null;
    dispatched_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    pos: { status: SyncStatus; receipt_number: string | null; synced_at: string | null };
    timeline: TimelineStep[];
    can: { pay: boolean; cancel: boolean };
    items_count?: number;
    items?: OrderItem[];
    delivery?: OrderDelivery | null;
    payment?: {
        reference: string;
        gateway: string;
        method_label: string;
        status: PaymentStatus;
        transaction_id: string | null;
        paid_at: string | null;
    } | null;
    histories?: { id: number; status: OrderStatus; status_label: string; note: string | null; by: string | null; at: string }[];
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/** Shape of a Laravel paginator passed straight to Inertia. */
export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
}

export interface KitchenStation {
    id: number;
    name: string;
    slug: string;
    sort_order: number;
    is_active: boolean;
}

export interface DeliveryZone {
    id: number;
    name: string;
    areas: string | null;
    fee: number;
    min_order: number;
    eta_minutes: number;
    is_active: boolean;
    sort_order: number;
}
