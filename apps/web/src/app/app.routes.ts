import { Routes } from '@angular/router';
import { adminGuard, authGuard } from './core/guards';

export const routes: Routes = [
  { path: '', pathMatch: 'full', loadComponent: () => import('./pages/home/home').then((m) => m.Home) },
  { path: 'menu', loadComponent: () => import('./pages/menu/menu').then((m) => m.Menu) },
  { path: 'menu/:category', loadComponent: () => import('./pages/menu/menu').then((m) => m.Menu) },
  { path: 'product/:slug', loadComponent: () => import('./pages/product/product').then((m) => m.ProductPage) },
  { path: 'cart', loadComponent: () => import('./pages/cart/cart').then((m) => m.CartPage) },
  { path: 'checkout', loadComponent: () => import('./pages/checkout/checkout').then((m) => m.Checkout) },
  { path: 'wishlist', loadComponent: () => import('./pages/wishlist/wishlist').then((m) => m.Wishlist) },
  { path: 'gallery', loadComponent: () => import('./pages/gallery/gallery').then((m) => m.Gallery) },
  { path: 'order/:id', loadComponent: () => import('./pages/order/order').then((m) => m.OrderPage) },
  { path: 'catering', loadComponent: () => import('./pages/catering/catering').then((m) => m.Catering) },
  { path: 'about', loadComponent: () => import('./pages/about/about').then((m) => m.About) },
  { path: 'contact', loadComponent: () => import('./pages/contact/contact').then((m) => m.Contact) },
  { path: 'faq', loadComponent: () => import('./pages/faq/faq').then((m) => m.Faq) },
  { path: 'legal/:doc', loadComponent: () => import('./pages/legal/legal').then((m) => m.Legal) },
  {
    path: 'account',
    children: [
      { path: 'login', loadComponent: () => import('./pages/account/login').then((m) => m.Login) },
      { path: 'register', loadComponent: () => import('./pages/account/register').then((m) => m.Register) },
      { path: 'forgot-password', loadComponent: () => import('./pages/account/forgot-password').then((m) => m.ForgotPassword) },
      { path: 'reset-password', loadComponent: () => import('./pages/account/reset-password').then((m) => m.ResetPassword) },
      { path: '', pathMatch: 'full', canActivate: [authGuard], loadComponent: () => import('./pages/account/dashboard').then((m) => m.AccountDashboard) },
    ],
  },
  {
    path: 'admin',
    canActivate: [adminGuard],
    loadChildren: () => import('./admin/admin.routes').then((m) => m.ADMIN_ROUTES),
  },
  { path: '**', loadComponent: () => import('./pages/not-found/not-found').then((m) => m.NotFound) },
];
