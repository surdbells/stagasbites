import { Routes } from '@angular/router';

export const ADMIN_ROUTES: Routes = [
  {
    path: '',
    loadComponent: () => import('./admin-layout').then((m) => m.AdminLayout),
    children: [
      { path: '', pathMatch: 'full', loadComponent: () => import('./dashboard').then((m) => m.AdminDashboard) },
      { path: 'orders', loadComponent: () => import('./orders').then((m) => m.AdminOrders) },
      { path: 'orders/:id', loadComponent: () => import('./order-detail').then((m) => m.AdminOrderDetail) },
      { path: 'products', loadComponent: () => import('./products').then((m) => m.AdminProducts) },
      { path: 'products/:id', loadComponent: () => import('./product-edit').then((m) => m.AdminProductEdit) },
      { path: 'categories', loadComponent: () => import('./categories').then((m) => m.AdminCategories) },
      { path: 'subscribers', loadComponent: () => import('./subscribers').then((m) => m.AdminSubscribers) },
      { path: 'coupons', loadComponent: () => import('./coupons').then((m) => m.AdminCoupons) },
      { path: 'settings', loadComponent: () => import('./settings').then((m) => m.AdminSettings) },
    ],
  },
];
